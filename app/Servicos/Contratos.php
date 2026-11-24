<?php

namespace App\Servicos;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\MensalidadeContrato;
use App\Models\MensalidadePausa;
use App\Models\OrdemServico;
use App\Models\Veiculo;
use App\Servicos\Inter\Boletos;
use App\Support\Atividades;
use App\Support\Formato;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contratos de mensalidade: criar, reajustar (nova versão), agendar encerramento e pausar.
 * A geração das mensalidades (ordens de serviço) usa estes contratos na etapa 3.
 */
class Contratos
{
    /**
     * O cliente tem desconto de pontualidade próprio (valor total digitado pelo usuário): quando uma mensalidade
     * muda, o sistema não recalcula, só avisa para revisar. Devolve o aviso, ou null se não houver desconto.
     */
    public static function revisarDesconto(?Cliente $cliente, string $oQue): ?string
    {
        $d = $cliente?->fresh()?->desconto_pontualidade;

        return $d !== null && (float) $d > 0
            ? $oQue.' '.$cliente->nome.' tem desconto de pontualidade de '.Formato::moeda((float) $d).' por mês. Confira se o valor continua certo.'
            : null;
    }

    private static function erro(string $campo, string $msg): never
    {
        throw ValidationException::withMessages([$campo => $msg]);
    }

    /** contrato que ainda não terminou para o veículo (vigente ou futuro) */
    public static function atual(Veiculo $veiculo): ?MensalidadeContrato
    {
        return $veiculo->contratos()->ativos()->orderByDesc('inicio')->first();
    }

    public static function criar(Veiculo $veiculo, array $d): MensalidadeContrato
    {
        if (! $veiculo->cliente_id) {
            self::erro('valor', 'Mensalidade só para veículo de cliente cadastrado.');
        }
        if (self::atual($veiculo)) {
            self::erro('valor', 'Este veículo já tem uma mensalidade ativa.');
        }
        $c = MensalidadeContrato::create([
            'unidade_id' => $d['unidade_id'],
            'cliente_id' => $veiculo->cliente_id,
            'veiculo_id' => $veiculo->id,
            'periodo' => $d['periodo'],
            'valor' => $d['valor'],
            'inicio' => $d['inicio'],
            'criado_por' => Auth::id(),
        ]);
        Atividades::registrar('mensalidade_criada', $veiculo->rotulo().': '.$c->periodo.', '.Formato::moeda($c->valor).' desde '.Carbon::parse($c->inicio)->format('d/m/Y'));

        return $c;
    }

    /** Reajuste: o contrato atual termina na véspera e uma nova versão começa na data. */
    public static function reajustar(MensalidadeContrato $c, float $valor, string $aPartir, ?string $periodo = null): MensalidadeContrato
    {
        $inicio = Carbon::parse($aPartir)->startOfDay();
        if ($inicio->lte($c->inicio)) {
            self::erro('a_partir', 'O reajuste precisa começar depois do início do contrato atual ('.$c->inicio->format('d/m/Y').').');
        }
        if ($c->fim && $inicio->gt($c->fim)) {
            self::erro('a_partir', 'O contrato termina em '.$c->fim->format('d/m/Y').'; o reajuste precisa começar antes disso.');
        }

        return DB::transaction(function () use ($c, $valor, $inicio, $periodo) {
            $fimAnterior = $c->fim;
            $c->update(['fim' => $inicio->copy()->subDay()->toDateString(), 'motivo_fim' => 'Reajuste']);
            $novo = MensalidadeContrato::create([
                'unidade_id' => $c->unidade_id,
                'cliente_id' => $c->cliente_id,
                'veiculo_id' => $c->veiculo_id,
                'periodo' => $periodo ?: $c->periodo,
                'valor' => $valor,
                'inicio' => $inicio->toDateString(),
                'fim' => $fimAnterior,
                'motivo_fim' => $fimAnterior ? $c->getOriginal('motivo_fim') : null,
                'anterior_id' => $c->id,
                'criado_por' => Auth::id(),
            ]);
            // pausas depois do reajuste passam para a nova versão
            MensalidadePausa::where('contrato_id', $c->id)->where('inicio', '>=', $inicio->toDateString())->update(['contrato_id' => $novo->id]);
            Atividades::registrar('mensalidade_reajustada', $c->veiculo->rotulo().': '.Formato::moeda($c->valor).' → '.Formato::moeda($valor).' a partir de '.$inicio->format('d/m/Y'));

            return $novo;
        });
    }

    /** Encerramento (agendado ou para hoje): a última mensalidade é a do mês do fim. */
    public static function encerrar(MensalidadeContrato $c, string $data, string $motivo): void
    {
        $fim = Carbon::parse($data)->startOfDay();
        if ($fim->lt($c->inicio)) {
            self::erro('data', 'O encerramento não pode ser antes do início do contrato ('.$c->inicio->format('d/m/Y').').');
        }
        $c->update(['fim' => $fim->toDateString(), 'motivo_fim' => $motivo]);
        Atividades::registrar('mensalidade_encerrada', $c->veiculo->rotulo().': termina em '.$fim->format('d/m/Y').' ('.$motivo.')');
    }

    public static function desfazerEncerramento(MensalidadeContrato $c): void
    {
        if (! $c->fim || $c->fim->lt(now()->startOfDay())) {
            self::erro('data', 'Só dá para desfazer um encerramento que ainda não aconteceu.');
        }
        $c->update(['fim' => null, 'motivo_fim' => null]);
        Atividades::registrar('mensalidade_encerramento_desfeito', $c->veiculo->rotulo());
    }

    /** mensalidade do veículo naquele mês (a que vale, não cancelada) */
    public static function mensalidadeDoMes(MensalidadeContrato $c, string $competencia): ?OrdemServico
    {
        return OrdemServico::where('tipo', 'mensalidade')->where('veiculo_id', $c->veiculo_id)->where('competencia', $competencia)
            ->where('situacao', '<>', 'cancelada')->orderBy('id')->first();
    }

    /** aviso de cobrança já emitida com o valor antigo (o boleto não muda sozinho) */
    private static function avisoCobranca(OrdemServico $o): ?string
    {
        $cob = $o->cobranca_id ? Cobranca::find($o->cobranca_id) : null;
        if (! $cob || ! in_array($cob->situacao, ['aberta', 'parcial'], true)) {
            return null;
        }

        return 'A cobrança nº '.$cob->numero.' foi emitida com o valor antigo'
            .(Boletos::ativo($cob) ? ' e tem boleto ativo: cancele o boleto e a cobrança e emita de novo.' : ': cancele e emita de novo, se for mandar ao cliente.');
    }

    /**
     * Pausa de meses inteiros (de/até, "2026-12"), com taxa de reserva (0 = grátis).
     * $mes: o que fazer com a mensalidade do primeiro mês, se ela já foi gerada (cliente parou no meio do mês):
     * ['acao' => proporcional|cancelar|manter, 'ultimo_dia' => data em que veio pela última vez, 'valor' => proporcional
     * ajustado (vazio = o calculado), 'credito' => já paga: lançar a diferença como crédito (true) ou não mexer (false)].
     *
     * @return array{0: MensalidadePausa, 1: list<string>} a pausa e os avisos para a tela
     */
    public static function pausar(MensalidadeContrato $c, string $deMes, string $ateMes, string $taxaTipo, float $taxaValor, ?string $motivo, ?array $mes = null): array
    {
        $inicio = Carbon::parse($deMes.'-01')->startOfDay();
        $fim = Carbon::parse($ateMes.'-01')->endOfMonth()->startOfDay();
        if ($fim->lt($inicio)) {
            self::erro('ate', 'O último mês da pausa precisa ser igual ou depois do primeiro.');
        }
        if ($inicio->lt($c->inicio->copy()->startOfMonth()) || ($c->fim && $fim->gt($c->fim->copy()->endOfMonth()))) {
            self::erro('de', 'A pausa precisa ficar dentro do período do contrato.');
        }
        $sobrepoe = $c->pausas()->where('inicio', '<=', $fim->toDateString())->where('fim', '>=', $inicio->toDateString())->exists();
        if ($sobrepoe) {
            self::erro('de', 'Já existe uma pausa nesse período.');
        }
        if ($taxaTipo === 'pct' && $taxaValor > 100) {
            self::erro('taxa_valor', 'A taxa em porcentagem vai até 100%.');
        }

        return DB::transaction(function () use ($c, $inicio, $fim, $taxaTipo, $taxaValor, $motivo, $mes) {
            $p = MensalidadePausa::create([
                'contrato_id' => $c->id,
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
                'taxa_tipo' => $taxaTipo,
                'taxa_valor' => $taxaValor,
                'motivo' => $motivo,
                'criado_por' => Auth::id(),
            ]);
            $taxa = $taxaValor > 0 ? ' com taxa '.($taxaTipo === 'pct' ? rtrim(rtrim(number_format($taxaValor, 2, ',', ''), '0'), ',').'%' : Formato::moeda($taxaValor)) : ' sem taxa';
            Atividades::registrar('mensalidade_pausada', $c->veiculo->rotulo().': '.$inicio->format('m/Y').' a '.$fim->format('m/Y').$taxa);

            return [$p, self::ajustarPrimeiroMes($c, $inicio, $mes ?? [])];
        });
    }

    /** mensalidade do mês em que a pausa começa, já gerada: proporcional dos dias que veio, cancelar ou manter */
    private static function ajustarPrimeiroMes(MensalidadeContrato $c, Carbon $inicio, array $mes): array
    {
        $o = self::mensalidadeDoMes($c, $inicio->format('Y-m'));
        $acao = $mes['acao'] ?? 'manter';
        if (! $o || $acao === 'manter') {
            return [];
        }
        if (! in_array($acao, ['proporcional', 'cancelar'], true)) {
            self::erro('mes_acao', 'Escolha o que fazer com a mensalidade do mês.');
        }
        $cheio = (float) $o->valor;
        $valor = 0.0;
        $descricao = null;
        if ($acao === 'proporcional') {
            $ultimo = Carbon::parse($mes['ultimo_dia'] ?? '')->startOfDay();
            if ($ultimo->format('Y-m') !== $inicio->format('Y-m')) {
                self::erro('mes_ultimo_dia', 'O último dia que ele veio precisa ser em '.$inicio->format('m/Y').'.');
            }
            [$calculado] = self::proporcionalAte($cheio, $ultimo);
            $valor = isset($mes['valor']) && $mes['valor'] !== '' && $mes['valor'] !== null ? round((float) $mes['valor'], 2) : $calculado;
            if ($valor <= 0 || $valor >= $cheio) {
                self::erro('mes_valor', 'O proporcional precisa ficar entre R$ 0,01 e '.Formato::moeda($cheio - 0.01).'.');
            }
            $descricao = 'Mensalidade proporcional 01/'.$ultimo->format('m').' a '.$ultimo->format('d/m').' (pausa)';
        }

        $avisos = [];
        if ($o->situacao === 'aberta') {
            if ($acao === 'proporcional') {
                Ordens::alterar($o, $valor, $o->vencimento?->toDateString(), $descricao);
            } else {
                Ordens::cancelar($o, 'Pausa: mensalidade de '.$inicio->format('m/Y').' cancelada');
            }
            if ($aviso = self::avisoCobranca($o)) {
                $avisos[] = $aviso;
            }

            return $avisos;
        }

        // já paga: a diferença vira crédito só se o usuário escolher
        $diferenca = round($cheio - $valor, 2);
        if (! array_key_exists('credito', $mes) || $mes['credito'] === null || $mes['credito'] === '') {
            self::erro('mes_credito', 'A mensalidade de '.$inicio->format('m/Y').' já está paga: escolha se a diferença de '.Formato::moeda($diferenca).' vira crédito.');
        }
        if (filter_var($mes['credito'], FILTER_VALIDATE_BOOLEAN)) {
            $credito = Ordens::lancarNaConta($c->cliente, 'credito', $diferenca, 'Crédito da pausa · mensalidade '.$inicio->format('m/Y'), null,
                'Pausa: '.($acao === 'proporcional' ? 'proporcional de '.Formato::moeda($valor).' no lugar de '.Formato::moeda($cheio) : 'mensalidade do mês cancelada'));
            $avisos[] = 'Crédito de '.Formato::moeda($diferenca).' lançado na conta (ordem #'.$credito->numero.').';
        } else {
            Atividades::registrar('mensalidade_pausa_sem_credito', $c->veiculo->rotulo().': mensalidade '.$inicio->format('m/Y').' paga, diferença de '.Formato::moeda($diferenca).' mantida');
        }

        return $avisos;
    }

    /** proporcional dos dias que veio (do dia 1 até o dia), mês comercial de 30 dias: [valor, dias] */
    public static function proporcionalAte(float $mensal, Carbon $ultimo): array
    {
        $dias = min($ultimo->day, 30);

        return [round($mensal * $dias / 30, 2), $dias];
    }

    /**
     * O cliente voltou no meio da pausa: a pausa termina no mês anterior (ou some, se começou no mesmo mês) e o resto
     * do mês da volta vira uma mensalidade proporcional (sem desconto de pontualidade). $quando: agora (vence hoje,
     * para receber já) ou proximo (vence no dia do cliente no mês seguinte e entra na próxima cobrança).
     *
     * @return array{0: ?OrdemServico, 1: list<string>}
     */
    public static function voltar(MensalidadePausa $p, string $data, string $quando, ?float $valorInformado = null): array
    {
        $volta = Carbon::parse($data)->startOfDay();
        if ($volta->lt($p->inicio) || $volta->gt($p->fim)) {
            self::erro('data', 'A volta precisa ser durante a pausa ('.$p->inicio->format('d/m/Y').' a '.$p->fim->format('d/m/Y').').');
        }
        $c = $p->contrato;
        $mes = $volta->format('Y-m');

        return DB::transaction(function () use ($p, $c, $volta, $mes, $quando, $valorInformado) {
            $avisos = [];
            $rotulo = $c->veiculo->rotulo();
            $resumo = $p->inicio->format('m/Y').' a '.$p->fim->format('m/Y');
            if ($volta->copy()->startOfMonth()->lte($p->inicio)) {
                $p->delete(); // voltou no mesmo mês em que parou: a pausa não cobre mês nenhum
            } else {
                $p->update(['fim' => $volta->copy()->startOfMonth()->subDay()->toDateString()]);
            }
            Atividades::registrar('mensalidade_pausa_volta', $rotulo.': pausa '.$resumo.', voltou em '.$volta->format('d/m/Y'));

            // reserva de vaga do mês da volta: em aberto, sai (o proporcional entra no lugar); paga, fica
            $reserva = self::mensalidadeDoMes($c, $mes);
            if ($reserva && str_contains(mb_strtolower($reserva->descricao), 'reserva')) {
                if ($reserva->situacao === 'aberta') {
                    Ordens::cancelar($reserva, 'Volta da pausa: trocada pelo proporcional');
                } else {
                    $avisos[] = 'A reserva de vaga de '.$volta->format('m/Y').' ('.Formato::moeda((float) $reserva->valor).') já estava paga e continua: lance um crédito se quiser descontar.';
                }
            }
            if ($volta->day === 1) {
                return [null, $avisos]; // voltou no dia 1: o mês é cheio, gerado como sempre
            }

            [$calculado, , $de, $ate] = Mensalidades::proporcional((float) $c->valor, $volta);
            $valor = $valorInformado !== null ? round($valorInformado, 2) : $calculado;
            if ($valor <= 0 || $valor > (float) $c->valor) {
                self::erro('valor', 'O proporcional precisa ficar entre R$ 0,01 e o valor da mensalidade.');
            }
            $vencimento = $quando === 'agora'
                ? today()
                : Mensalidades::vencimentoNoMes((int) $c->cliente->dia_vencimento, $volta->copy()->startOfMonth()->addMonth()->format('Y-m'));
            $o = Ordens::lancar([
                'tipo' => 'mensalidade',
                'unidade_id' => $c->unidade_id,
                'veiculo_id' => $c->veiculo_id,
                'contrato_id' => $c->id,
                'competencia' => $mes,
                'vencimento' => $vencimento->toDateString(),
                'valor' => $valor,
                'descricao' => "Mensalidade proporcional {$de} a {$ate} (volta da pausa)",
            ]);
            Atividades::registrar('mensalidade_gerada', $rotulo.': #'.$o->numero.' '.$o->descricao.', '.Formato::moeda($valor).', vencimento '.$vencimento->format('d/m/Y'));

            return [$o, $avisos];
        });
    }

    public static function removerPausa(MensalidadePausa $p): void
    {
        if ($p->inicio->lt(now()->startOfMonth())) {
            self::erro('pausa', 'Pausa que já começou não pode ser removida (os meses já foram tratados).');
        }
        Atividades::registrar('mensalidade_pausa_removida', $p->contrato->veiculo->rotulo().': '.$p->inicio->format('m/Y').' a '.$p->fim->format('m/Y'));
        $p->delete();
    }
}
