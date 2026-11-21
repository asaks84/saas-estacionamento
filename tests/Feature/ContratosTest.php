<?php

namespace Tests\Feature;

use App\Models\MensalidadeContrato;
use App\Models\MensalidadePausa;
use App\Models\OrdemServico;
use App\Servicos\Ordens;
use App\Support\EmpresaAtual;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mensalidade: reajuste por nova versão, encerramento agendado e pausa com taxa. */
class ContratosTest extends TestCase
{
    use RefreshDatabase;

    private function contrato(object $a, object $x, string $inicio = '2026-01-01'): MensalidadeContrato
    {
        return app(EmpresaAtual::class)->como($a->empresa->id, fn () => MensalidadeContrato::create([
            'unidade_id' => $a->unidade->id, 'cliente_id' => $x->cliente->id, 'veiculo_id' => $x->veiculo->id,
            'periodo' => 'Diurno', 'valor' => 200, 'inicio' => $inicio,
        ]));
    }

    public function test_reajuste_cria_nova_versao(): void
    {
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $this->actingAs($a->admin)->post("/mensalidades/{$c->id}/reajustar", ['valor' => 230, 'a_partir' => '2027-01-01'])->assertSessionHasNoErrors();

        app(EmpresaAtual::class)->definir($a->empresa->id);
        $this->assertSame('2026-12-31', $c->fresh()->fim->toDateString());
        $novo = MensalidadeContrato::where('anterior_id', $c->id)->firstOrFail();
        $this->assertSame('230.00', $novo->valor);
        $this->assertSame('2027-01-01', $novo->inicio->toDateString());
        $this->assertNull($novo->fim);
        // no dia de hoje ainda vale o antigo; em 2027, o novo
        $this->assertSame($c->id, MensalidadeContrato::vigentes(now())->value('id'));
        $this->assertSame($novo->id, MensalidadeContrato::vigentes(Carbon::parse('2027-02-10'))->value('id'));
    }

    public function test_reajuste_exige_permissao(): void
    {
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/reajustar", ['valor' => 1, 'a_partir' => '2027-01-01'])->assertForbidden();
    }

    public function test_um_contrato_ativo_por_veiculo(): void
    {
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $this->contrato($a, $x);
        $this->actingAs($a->operador)->post("/veiculos/{$x->veiculo->id}/mensalidade", [
            'periodo' => 'Noturno', 'valor' => 100, 'inicio' => '2026-11-01', 'unidade_id' => $a->unidade->id,
        ])->assertSessionHasErrors('valor');
    }

    public function test_encerramento_agendado_e_desfazer(): void
    {
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $data = now()->addMonths(2)->toDateString();
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/encerrar", ['data' => $data, 'motivo' => 'Vai mudar de cidade'])->assertSessionHasNoErrors();
        $this->assertSame($data, $c->fresh()->fim->toDateString());
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/desfazer-encerramento")->assertSessionHasNoErrors();
        $this->assertNull($c->fresh()->fim);
    }

    public function test_pausa_com_taxa_e_sem_sobrepor(): void
    {
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $de = now()->addMonth()->format('Y-m');
        $ate = now()->addMonths(2)->format('Y-m');
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/pausar", ['de' => $de, 'ate' => $ate, 'taxa_tipo' => 'pct', 'taxa_valor' => 30])->assertSessionHasNoErrors();
        app(EmpresaAtual::class)->definir($a->empresa->id);
        $p = MensalidadePausa::firstOrFail();
        $this->assertSame($de.'-01', $p->inicio->toDateString());
        $this->assertSame(60.0, $p->taxaSobre(200));
        $this->assertNotNull($c->fresh()->load('pausas')->pausaEm(now()->addMonth()));

        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/pausar", ['de' => $ate, 'ate' => $ate, 'taxa_tipo' => 'valor', 'taxa_valor' => 0])
            ->assertSessionHasErrors('de');
        $this->actingAs($a->operador)->delete("/pausas/{$p->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, MensalidadePausa::count());
    }

    /** mensalidade de novembro já gerada (R$ 200, vence dia 10) */
    private function mensalidadeNov(object $a, object $x, MensalidadeContrato $c, string $descricao = 'Mensalidade'): OrdemServico
    {
        return app(EmpresaAtual::class)->como($a->empresa->id, fn () => Ordens::lancar(['tipo' => 'mensalidade', 'cliente_id' => $x->cliente->id, 'veiculo_id' => $x->veiculo->id,
            'contrato_id' => $c->id, 'unidade_id' => $a->unidade->id, 'valor' => 200, 'descricao' => $descricao, 'data' => '2026-11-01', 'vencimento' => '2026-11-10', 'competencia' => '2026-11']));
    }

    public function test_pausa_no_meio_do_mes_cobra_o_proporcional_ou_cancela(): void
    {
        Carbon::setTestNow('2026-11-17 10:00');
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $o = $this->mensalidadeNov($a, $x, $c);
        // veio até o dia 15: 15/30 de R$ 200 = R$ 100
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/pausar", ['de' => '2026-11', 'ate' => '2026-12', 'taxa_tipo' => 'valor', 'taxa_valor' => 0,
            'mes_acao' => 'proporcional', 'mes_ultimo_dia' => '2026-11-15'])->assertSessionHasNoErrors();
        $o->refresh();
        $this->assertSame(['100.00', 'aberta', 'Mensalidade proporcional 01/11 a 15/11 (pausa)'], [$o->valor, $o->situacao, $o->descricao]);

        // outro cliente: cancelar a do mês
        $y = $this->criarCliente($a, 'BELTRANO', 'BBB2C33');
        $c2 = $this->contrato($a, $y);
        $o2 = $this->mensalidadeNov($a, $y, $c2);
        $this->actingAs($a->operador)->post("/mensalidades/{$c2->id}/pausar", ['de' => '2026-11', 'ate' => '2026-11', 'taxa_tipo' => 'valor', 'taxa_valor' => 0, 'mes_acao' => 'cancelar'])->assertSessionHasNoErrors();
        $this->assertSame('cancelada', $o2->fresh()->situacao);
        Carbon::setTestNow();
    }

    public function test_pausa_com_mensalidade_paga_pergunta_se_a_diferenca_vira_credito(): void
    {
        Carbon::setTestNow('2026-11-17 10:00');
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $o = $this->mensalidadeNov($a, $x, $c);
        $o->update(['situacao' => 'paga', 'pago_em' => now()]);
        $base = ['de' => '2026-11', 'ate' => '2026-11', 'taxa_tipo' => 'valor', 'taxa_valor' => 0, 'mes_acao' => 'proporcional', 'mes_ultimo_dia' => '2026-11-15'];
        // sem escolher: o sistema avisa e não grava a pausa
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/pausar", $base)->assertSessionHasErrors('mes_credito');
        app(EmpresaAtual::class)->definir($a->empresa->id);
        $this->assertSame(0, MensalidadePausa::count());
        // vira crédito: R$ 100 na conta, a mensalidade paga fica como está
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/pausar", $base + ['mes_credito' => true])->assertSessionHasNoErrors()->assertSessionHas('aviso');
        $credito = OrdemServico::where('tipo', 'credito')->firstOrFail();
        $this->assertSame(['100.00', 'aberta'], [$credito->valor, $credito->situacao]);
        $this->assertSame(['200.00', 'paga'], [$o->fresh()->valor, $o->fresh()->situacao]);
        Carbon::setTestNow();
    }

    public function test_mensalidade_do_sistema_atual_nao_muda_no_paralelo(): void
    {
        Carbon::setTestNow('2026-11-17 10:00');
        config(['erp.espelho_ativo' => true]);
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $o = $this->mensalidadeNov($a, $x, $c);
        $o->forceFill(['legado_id' => 777])->save();
        $this->actingAs($a->operador)->post("/mensalidades/{$c->id}/pausar", ['de' => '2026-11', 'ate' => '2026-11', 'taxa_tipo' => 'valor', 'taxa_valor' => 0, 'mes_acao' => 'cancelar'])
            ->assertSessionHasErrors('mes_acao');
        $this->assertSame('aberta', $o->fresh()->situacao);
        Carbon::setTestNow();
    }

    public function test_volta_no_meio_da_pausa_gera_o_proporcional(): void
    {
        Carbon::setTestNow('2026-12-11 10:00');
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $p = app(EmpresaAtual::class)->como($a->empresa->id, fn () => MensalidadePausa::create(['contrato_id' => $c->id, 'inicio' => '2026-11-01', 'fim' => '2027-01-31', 'taxa_tipo' => 'valor', 'taxa_valor' => 0]));
        // voltou dia 11/12, vai para a conta de janeiro: 20/30 de R$ 200
        $this->actingAs($a->operador)->post("/pausas/{$p->id}/voltar", ['data' => '2026-12-11', 'quando' => 'proximo'])->assertSessionHasNoErrors();
        app(EmpresaAtual::class)->definir($a->empresa->id);
        $this->assertSame('2026-11-30', $p->fresh()->fim->toDateString());
        $o = OrdemServico::where('tipo', 'mensalidade')->firstOrFail();
        $this->assertSame(['133.33', '2026-12', '2027-01-10', 'Mensalidade proporcional 11/12 a 31/12 (volta da pausa)'],
            [$o->valor, $o->competencia, $o->vencimento->toDateString(), $o->descricao]);
        Carbon::setTestNow();
    }

    public function test_volta_para_pagar_agora_vai_para_receber_e_no_mesmo_mes_tira_a_pausa(): void
    {
        Carbon::setTestNow('2026-11-20 10:00');
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        $p = app(EmpresaAtual::class)->como($a->empresa->id, fn () => MensalidadePausa::create(['contrato_id' => $c->id, 'inicio' => '2026-11-01', 'fim' => '2026-11-30', 'taxa_tipo' => 'valor', 'taxa_valor' => 0]));
        $this->actingAs($a->operador)->post("/pausas/{$p->id}/voltar", ['data' => '2026-11-20', 'quando' => 'agora', 'valor' => 70])
            ->assertRedirect("/clientes/{$x->cliente->id}/receber");
        app(EmpresaAtual::class)->definir($a->empresa->id);
        $this->assertSame(0, MensalidadePausa::count());
        $o = OrdemServico::where('tipo', 'mensalidade')->firstOrFail();
        $this->assertSame(['70.00', '2026-11-20'], [$o->valor, $o->vencimento->toDateString()]);
        Carbon::setTestNow();
    }

    public function test_avisa_para_revisar_o_desconto_quando_a_mensalidade_muda(): void
    {
        $a = $this->criarEmpresa('a');
        $x = $this->criarCliente($a);
        $c = $this->contrato($a, $x);
        // sem desconto do cliente: nenhum aviso
        $this->actingAs($a->admin)->post("/mensalidades/{$c->id}/reajustar", ['valor' => 230, 'a_partir' => now()->addMonth()->startOfMonth()->toDateString()])->assertSessionMissing('revisar_desconto');
        $x->cliente->forceFill(['desconto_pontualidade' => 40])->save();
        app(EmpresaAtual::class)->definir($a->empresa->id);
        $novo = MensalidadeContrato::where('anterior_id', $c->id)->firstOrFail();
        $this->actingAs($a->admin)->post("/mensalidades/{$novo->id}/reajustar", ['valor' => 250, 'a_partir' => now()->addMonths(2)->startOfMonth()->toDateString()])
            ->assertSessionHas('revisar_desconto', fn ($m) => str_contains($m, 'R$ 40,00'));
        $this->actingAs($a->admin)->post("/veiculos/{$x->veiculo->id}/remover")->assertSessionHas('revisar_desconto');
    }
}
