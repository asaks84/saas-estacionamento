<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Cobranca;
use App\Models\Veiculo;
use App\Servicos\Cobrancas;
use App\Servicos\FormasPagamento;
use App\Servicos\Inter\Boletos;
use App\Servicos\Ordens;
use App\Servicos\Pontualidade;
use App\Support\EmpresaAtual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PontualidadeTest extends TestCase
{
    use RefreshDatabase;

    private object $a;

    private object $x;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-17 10:00');
        $this->a = $this->criarEmpresa('a');
        $this->x = $this->criarCliente($this->a, 'MARIA DA SILVA');
        app(EmpresaAtual::class)->definir($this->a->empresa->id);
        Auth::login($this->a->admin);
        FormasPagamento::garantirPadrao();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** desde 21/11/2026 o desconto vale em qualquer forma de pagamento; "desligado" = sem desconto configurado */
    private function config(bool $comDesconto): void
    {
        $this->a->empresa->update(['configuracoes' => $comDesconto ? ['boleto_desconto_tipo' => 'valor', 'boleto_desconto_valor' => 20] : ['boleto_desconto_tipo' => 'nenhum']]);
    }

    private function cobranca(): Cobranca
    {
        // mensalidade do mês (com desconto) + uma lavagem (sem desconto)
        Ordens::lancar(['tipo' => 'mensalidade', 'cliente_id' => $this->x->cliente->id, 'valor' => 250, 'descricao' => 'Mensalidade', 'data' => '2026-11-01', 'vencimento' => '2026-11-20', 'competencia' => '2026-11']);
        Ordens::lancar(['tipo' => 'avulso', 'cliente_id' => $this->x->cliente->id, 'valor' => 30, 'descricao' => 'Lavagem', 'data' => '2026-11-05', 'vencimento' => '2026-11-05']);

        return Cobrancas::emitir($this->x->cliente, '2026-11', '2026-11-20');
    }

    public function test_sem_desconto_configurado_nao_muda_nada(): void
    {
        $this->config(false);
        $c = $this->cobranca();
        $this->assertNull(Pontualidade::resumo($c));
        $this->actingAs($this->a->admin)->get('/cobrancas/'.$c->id)->assertInertia(fn ($p) => $p->where('pontualidade', null));
        // operador sem permissão de desconto continua barrado
        $this->actingAs($this->a->operador)->post('/clientes/'.$this->x->cliente->id.'/receber', ['ordens' => $c->itens->pluck('ordem_id')->all(), 'valor' => 260,
            'forma_pagamento_id' => FormasPagamento::naoInformada()->id, 'recebido_em' => '2026-11-17', 'falta' => 'desconto', 'motivo' => 'x'])->assertSessionHasErrors('falta');
    }

    public function test_ligada_aparece_e_entra_no_balcao_ate_o_vencimento(): void
    {
        $this->config(true);
        $c = $this->cobranca();
        $this->assertSame(['valor' => 20.0, 'ate' => '2026-11-20', 'a_pagar' => 260.0], Pontualidade::resumo($c));
        $this->actingAs($this->a->admin)->get('/cobrancas/'.$c->id)->assertInertia(fn ($p) => $p->where('pontualidade.a_pagar', 260));
        $this->actingAs($this->a->admin)->get('/clientes/'.$this->x->cliente->id.'/receber')->assertInertia(fn ($p) => $p->where('pontualidade.0.valor', 20)->where('pontualidade.0.ate', '2026-11-20'));

        // operador sem permissão de desconto: pontualidade é regra da empresa, entra
        $ordens = $c->itens->pluck('ordem_id')->all();
        $this->actingAs($this->a->operador)->post('/clientes/'.$this->x->cliente->id.'/receber', ['ordens' => $ordens, 'valor' => 260,
            'forma_pagamento_id' => FormasPagamento::naoInformada()->id, 'recebido_em' => '2026-11-17', 'falta' => 'desconto'])->assertSessionHasNoErrors();
        $aj = Ajuste::where('tipo', 'desconto')->first();
        $this->assertSame([20.0, 'Desconto de pontualidade'], [(float) $aj->valor, $aj->motivo]);
        $this->assertSame('paga', $c->refresh()->situacao);
    }

    public function test_depois_do_vencimento_ou_acima_do_desconto_nao_vale(): void
    {
        $this->config(true);
        $c = $this->cobranca();
        $ordens = $c->itens->pluck('ordem_id')->all();
        $forma = FormasPagamento::naoInformada()->id;
        // mais desconto do que o de pontualidade: precisa da permissão
        $this->actingAs($this->a->operador)->post('/clientes/'.$this->x->cliente->id.'/receber', ['ordens' => $ordens, 'valor' => 250,
            'forma_pagamento_id' => $forma, 'recebido_em' => '2026-11-17', 'falta' => 'desconto', 'motivo' => 'x'])->assertSessionHasErrors('falta');
        // pago depois do vencimento: sem desconto
        Carbon::setTestNow('2026-11-21 10:00');
        $this->assertNull(Pontualidade::resumo($c->refresh()));
        $this->actingAs($this->a->operador)->post('/clientes/'.$this->x->cliente->id.'/receber', ['ordens' => $ordens, 'valor' => 260,
            'forma_pagamento_id' => $forma, 'recebido_em' => '2026-11-21', 'falta' => 'desconto', 'motivo' => 'x'])->assertSessionHasErrors('falta');
        // só a lavagem marcada (sem a mensalidade): não tem desconto
        Carbon::setTestNow('2026-11-17 10:00');
        $lavagem = $c->itens->firstWhere('tipo', 'avulso')->ordem_id;
        $this->actingAs($this->a->operador)->post('/clientes/'.$this->x->cliente->id.'/receber', ['ordens' => [$lavagem], 'valor' => 10,
            'forma_pagamento_id' => $forma, 'recebido_em' => '2026-11-17', 'falta' => 'desconto', 'motivo' => 'x'])->assertSessionHasErrors('falta');
    }

    public function test_primeira_mensalidade_e_desconto_negociado_nao_tem(): void
    {
        $this->config(true);
        $c = $this->cobranca();
        $c->update(['desconto' => 15, 'desconto_motivo' => 'negociado']);
        $this->assertNull(Pontualidade::resumo($c->refresh()));
    }

    public function test_regras_ficam_em_configuracoes_cobrancas(): void
    {
        $base = ['boleto_multa_tipo' => 'nenhuma', 'boleto_juros_tipo' => 'nenhum', 'boleto_desconto_tipo' => 'valor', 'boleto_desconto_valor' => 20, 'boleto_baixa_dias' => 30];
        $this->actingAs($this->a->admin)->put('/configuracoes/cobrancas/regras', $base)->assertSessionHasNoErrors();
        $this->actingAs($this->a->admin)->get('/configuracoes/pagamentos')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Configuracoes/Pagamentos')->where('regras.boleto_desconto_valor', 20)->where('regras.boleto_baixa_dias', 30));
        $this->actingAs($this->a->operador)->put('/configuracoes/cobrancas/regras', $base)->assertForbidden();
        // sem opção "só no boleto": vale no balcão também
        $c = $this->cobranca();
        $this->assertSame(20.0, Pontualidade::resumo($c)['valor']);
    }

    public function test_desconto_do_cliente_vale_no_lugar_da_regra_uma_vez_por_mes(): void
    {
        $this->config(true);
        $outro = Veiculo::create(['cliente_id' => $this->x->cliente->id, 'tipo_veiculo_id' => $this->a->tipo->id, 'placa' => 'XYZ9K88']);
        // 2 carros: o usuário digita o total (R$ 40), não R$ 20 por mensalidade calculado pelo sistema
        Ordens::lancar(['tipo' => 'mensalidade', 'cliente_id' => $this->x->cliente->id, 'veiculo_id' => $outro->id, 'valor' => 250, 'descricao' => 'Mensalidade', 'data' => '2026-11-01', 'vencimento' => '2026-11-20', 'competencia' => '2026-11']);
        $this->actingAs($this->a->admin)->put('/clientes/'.$this->x->cliente->id.'/desconto-pontualidade', ['valor' => 35])->assertSessionHasNoErrors();
        $c = $this->cobranca();
        $this->assertSame(35.0, Pontualidade::resumo($c)['valor']);
        $this->assertSame(35.0, Boletos::descontoPontualidade($c, Boletos::regras(null, $this->x->cliente->fresh())));
        // 0 = sem desconto para este cliente, mesmo com a regra da empresa
        $this->actingAs($this->a->admin)->put('/clientes/'.$this->x->cliente->id.'/desconto-pontualidade', ['valor' => 0])->assertSessionHasNoErrors();
        $this->assertNull(Pontualidade::resumo($c->refresh()));
        // vazio = volta para a regra da empresa (R$ 20 por mensalidade: 2 × 20)
        $this->actingAs($this->a->admin)->put('/clientes/'.$this->x->cliente->id.'/desconto-pontualidade', ['valor' => null])->assertSessionHasNoErrors();
        $this->assertSame(40.0, Pontualidade::resumo($c->refresh())['valor']);
        // quem não pode mudar valor de mensalidade não mexe
        $this->actingAs($this->a->operador)->put('/clientes/'.$this->x->cliente->id.'/desconto-pontualidade', ['valor' => 99])->assertForbidden();
    }

    public function test_proporcional_e_pausa_nao_tem_desconto(): void
    {
        $this->x->cliente->update(['desconto_pontualidade' => 20]);
        Ordens::lancar(['tipo' => 'mensalidade', 'cliente_id' => $this->x->cliente->id, 'valor' => 125, 'descricao' => 'Mensalidade proporcional 01/11 a 15/11 (pausa)', 'data' => '2026-11-01', 'vencimento' => '2026-11-20', 'competencia' => '2026-11']);
        $c = Cobrancas::emitir($this->x->cliente, '2026-11', '2026-11-20');
        $this->assertNull(Pontualidade::resumo($c));
    }
}
