<?php

namespace App\Modulos\Estacionamento\Models;

use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Modelo;

/** Etapa da lavagem configurada pela empresa. Aguardando e entregue são fixas (primeira e última). */
class EtapaLavagem extends Modelo
{
    use PertenceAEmpresa;

    protected $table = 'etapas_lavagem';

    protected function casts(): array
    {
        return ['ativa' => 'boolean', 'fixa' => 'boolean'];
    }
}
