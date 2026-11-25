<?php

namespace App\Modulos\Estacionamento\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaPacote extends Model
{
    public $timestamps = false;

    protected $table = 'tarifa_pacotes';

    protected $guarded = ['id'];
}
