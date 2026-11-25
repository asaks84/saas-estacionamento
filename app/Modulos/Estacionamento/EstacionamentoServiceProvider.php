<?php

namespace App\Modulos\Estacionamento;

use App\Models\Aparelho;
use App\Models\Cliente;
use App\Models\OrdemServico;
use App\Models\PdvAcao;
use App\Models\Usuario;
use App\Modulos\Estacionamento\Models\Convenio;
use App\Modulos\Estacionamento\Models\Estadia;
use App\Modulos\Estacionamento\Servicos\Convenios;
use App\Modulos\Estacionamento\Servicos\EtapasLavagem;
use App\Modulos\Estacionamento\Servicos\Pdv\EstadiasSemInternet;
use App\Modulos\Modulo;
use App\Support\Placa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Módulo Estacionamento: pátio, estadias, tabelas de estadia (tarifador), convênios, lavagens com etapas
 * e as telas do PDV para estadia e lavagem. Regras em docs/05-regras-de-negocio.md.
 * Veículo, tipo de veículo e mensalidade ainda moram no núcleo (o dinheiro do núcleo depende deles);
 * ver docs/saas/04-multiplos-produtos.md, item "Ajustes no núcleo".
 */
class EstacionamentoServiceProvider extends Modulo
{
    public function chave(): string
    {
        return 'estacionamento';
    }

    public function nome(): string
    {
        return 'Estacionamento';
    }

    public function bloqueiosAnonimizar(Cliente $c): array
    {
        $b = [];
        if (Estadia::where('cliente_id', $c->id)->where('situacao', 'patio')->exists()) {
            $b[] = 'Está com veículo no pátio.';
        }
        if (OrdemServico::where('cliente_id', $c->id)->where('tipo', 'servico')->where('situacao', '<>', 'cancelada')
            ->whereNotNull('etapa')->where('etapa', '<>', 'entregue')->exists()) {
            $b[] = 'Tem lavagem em andamento.';
        }
        if (Convenio::where('cliente_id', $c->id)->where('ativo', true)->exists()) {
            $b[] = 'É um convênio ativo. Desative o convênio antes.';
        }

        return $b;
    }

    public function dadosPessoais(Cliente $c): array
    {
        $dataHora = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y H:i') : null;

        return [
            'estadias' => Estadia::where('cliente_id', $c->id)->orderBy('entrada')->get()
                ->map(fn ($e) => ['numero' => $e->numero_impresso ?: $e->numero, 'entrada' => $dataHora($e->entrada), 'saida' => $dataHora($e->saida),
                    'placa' => $e->placa ? Placa::formatar($e->placa) : $e->identificacao, 'valor' => (float) $e->valor_cliente])->values(),
        ];
    }

    public function anonimizar(Cliente $c, Collection $veiculoIds): void
    {
        $nulos = fn (array $cols) => array_fill_keys($cols, null);
        $estadias = DB::table('estadias')->where('cliente_id', $c->id)->orWhereIn('veiculo_id', $veiculoIds->all() ?: [0])->pluck('id');
        DB::table('estadias')->whereIn('id', $estadias)->update($nulos(['placa', 'identificacao', 'modelo', 'celular', 'retirada_nome', 'retirada_documento', 'observacao']));
        DB::table('pdv_acoes')->whereIn('estadia_id', $estadias)->update(['dados' => null]);
    }

    public function inicio(Usuario $u): array
    {
        return [
            // convênios com fechamento hoje (ou atrasado): botão "Gerar cobrança"
            'convenios_fechar' => $u->pode('convenios') ? Convenios::aFechar()->map(fn ($x) => ['id' => $x['convenio']->id,
                'nome' => $x['convenio']->cliente?->nome, 'dia' => $x['dia']->toDateString(), 'itens' => $x['itens'], 'total' => $x['total'],
                'fechamento' => $x['convenio']->resumoFechamento()])->values() : [],
        ];
    }

    public function compartilhado(?Usuario $u): array
    {
        return [
            // pendências do PDV sem internet (8.7), na aba do Pátio
            'pdv_pendencias' => fn () => $u ? PdvAcao::where('situacao', 'pendente')->count() : 0,
        ];
    }

    /** Início do PDV: carros no pátio e lavagens em andamento */
    public function pdvInicio(): array
    {
        return [
            'patio' => Estadia::where('situacao', 'patio')->count(),
            'lavagens' => OrdemServico::where('tipo', 'servico')->whereNull('principal_id')->whereNotNull('etapa')
                ->where('situacao', '<>', 'cancelada')->where('etapa', '<>', EtapasLavagem::ULTIMA)->count(),
        ];
    }

    public function pdvPacote(Aparelho $a): array
    {
        return EstadiasSemInternet::pacote($a);
    }

    public function pdvAcoes(): array
    {
        return EstadiasSemInternet::acoes();
    }
}
