<?php

namespace App\Servicos;

use App\Models\ServicoPreco;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Preço vigente de serviço para um tipo de veículo, numa unidade, numa data (a tabela de estadia fica
 * no módulo Estacionamento: App\Modulos\Estacionamento\Servicos\Tarifas).
 * A regra da unidade vence a da empresa; cada data usa a versão vigente naquele dia.
 * Ver docs/05-regras-de-negocio.md (vigência de preços).
 */
class Precos
{
    public static function vigente(Builder $q, string $dia, string $ini = 'vigencia_inicio', string $fim = 'vigencia_fim'): Builder
    {
        return $q->where($ini, '<=', $dia)->where(fn ($q) => $q->whereNull($fim)->orWhere($fim, '>=', $dia));
    }

    public static function servico(int $servicoId, int $tipoVeiculoId, ?int $unidadeId = null, ?CarbonInterface $data = null): ?float
    {
        $dia = ($data ?? now())->toDateString();
        $q = ServicoPreco::where('servico_id', $servicoId)->where('tipo_veiculo_id', $tipoVeiculoId)
            ->where(fn ($q) => $q->whereNull('unidade_id')->when($unidadeId, fn ($q) => $q->orWhere('unidade_id', $unidadeId)));
        self::vigente($q, $dia);
        $preco = $q->orderByRaw('unidade_id IS NULL')->orderByDesc('vigencia_inicio')->first();

        return $preco ? (float) $preco->valor : null;
    }
}
