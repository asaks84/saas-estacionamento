<?php

namespace App\Modulos\Estacionamento\Servicos\Pdv;

use App\Models\Aparelho;
use App\Models\FormaPagamento;
use App\Models\MensalidadeContrato;
use App\Models\PdvAcao;
use App\Models\Usuario;
use App\Modulos\Estacionamento\Http\Controllers\EstadiaController;
use App\Modulos\Estacionamento\Models\Convenio;
use App\Modulos\Estacionamento\Models\Estadia;
use App\Modulos\Estacionamento\Servicos\Estadias;
use App\Modulos\Estacionamento\Servicos\Tarifas;
use App\Servicos\FormasPagamento;
use App\Servicos\Ordens;
use App\Support\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Estacionamento no modo sem internet do PDV (8.7). A base (fila, código único, PIN trancado, hora do celular,
 * pendências) é do núcleo: App\Servicos\Pdv\SemInternet. Aqui fica o que é do estacionamento:
 * - pacote(): tabelas de estadia, convênios, pátio, mensalistas e o último número de ticket;
 * - acoes(): entrada e saída de estadia feitas sem internet.
 * Vale o que o cliente pagou: se o cálculo daqui der diferente do celular, registra o que foi cobrado e
 * aponta a diferença.
 */
class EstadiasSemInternet
{
    /**
     * saída que chega mais de tantos minutos depois de feita é lançada (o cliente já pagou), mas vai para o gerente
     * conferir a hora e o valor: hora inventada ou relógio do celular atrasado baratearia a estadia (auditoria 21/11/2026)
     */
    public const SAIDA_CONFERIR_MIN = 60;

    /** ações que o celular faz sem internet (formato de App\Modulos\Modulo::pdvAcoes) */
    public static function acoes(): array
    {
        return [
            'estadia_entrada' => ['rotulo' => 'Entrada de estadia', 'executar' => [self::class, 'entrada']],
            'estadia_saida' => ['rotulo' => 'Saída de estadia', 'executar' => [self::class, 'saida'], 'conferir' => [self::class, 'conferirSaida']],
        ];
    }

    /** a parte do estacionamento na cópia que o celular guarda */
    public static function pacote(Aparelho $a): array
    {
        FormasPagamento::garantirPadrao();
        $unidade = Ordens::unidadePadrao();
        $tipos = collect(Estadias::tiposComTabela())->filter(fn ($t) => $t['tabela'])->values();
        $tabelas = [];
        foreach ($tipos as $t) {
            $tarifa = Tarifas::vigente($t['id'], $unidade);
            if ($tarifa) {
                $tabelas[$t['id']] = ['id' => $tarifa->id, 'nome' => $tarifa->nome, 'regras' => $tarifa->regras()];
            }
        }
        $convenios = Convenio::with('cliente:id,nome', 'regras')->where('ativo', true)->get()
            ->filter(fn (Convenio $c) => $c->regra(null))
            ->map(fn (Convenio $c) => self::convenio($c))->sortBy('nome')->values();

        $patio = Estadia::with(['tipo:id,nome', 'cliente:id,nome', 'convenio.cliente:id,nome', 'convenio.regras', 'pacote:id,nome'])
            ->where('situacao', 'patio')->orderByDesc('entrada')->get()
            ->map(fn (Estadia $e) => EstadiaController::linha($e) + [
                'tipo_veiculo_id' => $e->tipo_veiculo_id,
                'identificacao' => $e->identificacao,
                'pacote_id' => $e->pacote_id,
                'valor_pacote' => $e->valor_pacote !== null ? (float) $e->valor_pacote : null,
                'numero_impresso' => $e->numero_impresso,
            ]);

        $mensalistas = MensalidadeContrato::with('veiculo.cliente:id,nome')->vigentes()->get()
            ->filter(fn ($m) => $m->veiculo?->placa)
            ->map(fn ($m) => ['placa' => $m->veiculo->placa, 'cliente' => $m->veiculo->cliente?->nome, 'cliente_id' => $m->veiculo->cliente_id,
                'tipo_id' => $m->veiculo->tipo_veiculo_id, 'modelo' => trim($m->veiculo->marca.' '.$m->veiculo->modelo) ?: null])
            ->unique('placa')->values();

        return [
            'opcoes' => [
                'tipos' => $tipos,
                'formas' => FormaPagamento::where('ativa', true)->orderBy('ordem')->get(['id', 'nome', 'tipo', 'parcelas_max']),
                'convenios' => $convenios->map(fn ($c) => collect($c)->only(['id', 'nome', 'resumo'])->all())->values(),
            ],
            'tabelas' => (object) $tabelas,
            'convenios' => $convenios,
            'patio' => $patio,
            'mensalistas' => $mensalistas,
            'ultimo_numero' => (int) (DB::table('numeracoes')->where('empresa_id', $a->empresa_id)->where('tipo', 'estadia')->value('ultimo')
                ?? Estadia::max('numero')),
        ];
    }

    /** convênio com a regra da estadia (para o celular dividir o valor igual ao servidor) */
    private static function convenio(Convenio $c): array
    {
        $r = $c->regra(null);

        return ['id' => $c->id, 'nome' => $c->cliente?->nome, 'resumo' => $c->resumo(),
            'regra' => $r ? ['modo' => $r->modo, 'limite_valor' => $r->limite_valor !== null ? (float) $r->limite_valor : null, 'limite_min' => $r->limite_min !== null ? (int) $r->limite_min : null] : null];
    }

    public static function entrada(PdvAcao $acao, array $d): void
    {
        $e = Estadias::entrar([
            'placa' => $d['placa'] ?? null, 'identificacao' => $d['identificacao'] ?? null, 'modelo' => $d['modelo'] ?? null,
            'tipo_veiculo_id' => $d['tipo_veiculo_id'] ?? null, 'pacote_id' => $d['pacote_id'] ?? null, 'pagar_agora' => ! empty($d['pagar_agora']),
            'forma_pagamento_id' => $d['forma_pagamento_id'] ?? null, 'valor_recebido' => $d['valor_recebido'] ?? null,
            // o celular já avisou que era mensalista e o operador confirmou
            'confirmar_mensalista' => true,
            'entrada' => now()->format('Y-m-d H:i'), 'uuid' => $acao->uuid, 'codigo' => $d['codigo'] ?? null, 'numero_impresso' => $d['numero'] ?? null,
        ]);
        $acao->estadia_id = $e->id;
        if (! empty($d['codigo']) && $e->codigo !== mb_strtolower((string) $d['codigo'])) {
            // não deveria acontecer (código repetido): o QR impresso não abre esta estadia
            $acao->situacao = 'pendente';
            $acao->mensagem = 'O código do QR impresso já existia: o ticket '.$e->numero.' ('.$e->rotulo().') ficou com outro código. Ache pela placa ou pelo número.';
        }
    }

    public static function saida(PdvAcao $acao, array $d): void
    {
        $codigo = mb_strtolower((string) ($d['codigo'] ?? ''));
        $e = $codigo ? Estadia::where('codigo', $codigo)->first() : null;
        if (! $e) {
            throw ValidationException::withMessages(['codigo' => 'Ticket não encontrado (a entrada não subiu ou foi feita em outro aparelho sem internet). Saída de '
                .($d['rotulo'] ?? 'veículo').' às '.now()->format('d/m H:i').self::cobrado($d).'.']);
        }
        $acao->estadia_id = $e->id;
        if ($e->situacao !== 'patio') {
            throw ValidationException::withMessages(['saida' => 'Saída feita no celular às '.now()->format('d/m H:i').self::cobrado($d).', mas o ticket '.$e->numero.' ('.$e->rotulo().') já estava '
                .($e->situacao === 'cancelada' ? 'cancelado' : 'finalizado'.($e->saida ? ' ('.$e->saida->format('d/m H:i').')' : '')).' na gestão.']);
        }
        $c = Estadias::calcular($e, now());
        $destino = $d['destino'] ?? 'pago';
        $cobrado = isset($d['cobrado']) ? round((float) $d['cobrado'], 2) : null;
        $dados = [
            'destino' => $destino, 'saida' => now()->format('Y-m-d H:i'),
            'convenio_id' => $d['convenio_id'] ?? null, 'conta_cliente_id' => $d['conta_cliente_id'] ?? null,
            'forma_pagamento_id' => $d['forma_pagamento_id'] ?? null, 'valor_recebido' => $d['valor_recebido'] ?? null,
            'perdido' => ! empty($d['perdido']), 'retirada_nome' => $d['retirada_nome'] ?? null, 'retirada_documento' => $d['retirada_documento'] ?? null,
        ];
        if ($cobrado !== null && $destino !== 'mensalista') {
            $dados['cobrado_no_celular'] = $cobrado;
        }
        Estadias::sair($e, $dados);
        if ($cobrado !== null && $destino !== 'mensalista' && abs($cobrado - $c['a_pagar']) > 0.009) {
            $acao->situacao = 'pendente';
            $acao->mensagem = 'Diferença de cálculo no ticket '.$e->numero.' ('.$e->rotulo().'): o celular cobrou '.Formato::moeda($cobrado)
                .' e a gestão calcula '.Formato::moeda($c['a_pagar']).'. Ficou lançado o que foi cobrado.';
        }
    }

    /** saída lançada, mas enviada muito depois de feita: o gerente confere a hora e o valor */
    public static function conferirSaida(PdvAcao $acao, Carbon $feita, Carbon $recebida, Usuario $u): ?string
    {
        if ((int) $feita->diffInMinutes($recebida) <= self::SAIDA_CONFERIR_MIN) {
            return null;
        }

        return 'Saída feita no celular às '.$feita->format('d/m H:i').' e enviada só às '.$recebida->format('d/m H:i')
            .' por '.$u->nome.': já está lançada; confira se a hora e o valor batem.';
    }

    private static function cobrado(array $d): string
    {
        return isset($d['cobrado']) && (float) $d['cobrado'] > 0 ? ', cobrado '.Formato::moeda($d['cobrado']).(($d['forma_nome'] ?? null) ? ' no '.$d['forma_nome'] : '') : '';
    }
}
