<?php

namespace App\Modulos\Estacionamento\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaFaixa extends Model
{
    public $timestamps = false;

    protected $table = 'tarifa_faixas';

    protected $guarded = ['id'];
}
