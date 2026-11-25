<?php

namespace App\Modulos\Estacionamento\Servicos;

use App\Modulos\Estacionamento\Models\Tarifa;
use App\Servicos\Precos;
use Carbon\CarbonInterface;

/**
 * Tabela de estadia vigente para um tipo de veículo, numa unidade, numa data.
 * A regra da unidade vence a da empresa; cada data usa a versão vigente naquele dia.
 * Ver docs/05-regras-de-negocio.md (vigência de preços).
 */
class Tarifas
{
    public static function vigente(int $tipoVeiculoId, ?int $unidadeId = null, ?CarbonInterface $data = null): ?Tarifa
    {
        $dia = ($data ?? now())->toDateString();
        $q = Tarifa::whereHas('tipos', fn ($q) => $q->where('tipos_veiculo.id', $tipoVeiculoId))
            ->where(fn ($q) => $q->whereNull('unidade_id')->when($unidadeId, fn ($q) => $q->orWhere('unidade_id', $unidadeId)));
        Precos::vigente($q, $dia);

        // unidade antes da empresa; dentro do mesmo nível, a versão mais nova
        return $q->orderByRaw('unidade_id IS NULL')->orderByDesc('vigencia_inicio')->with(['faixas', 'pacotes'])->first();
    }
}
