<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Modulos\Modulos;
use Illuminate\Http\JsonResponse;

/** API do PDV, visual novo (8.8): os números do Início. */
class InicioPdvController extends Controller
{
    /** o caixa aberto (sem abrir um caixa só por olhar) e os números de cada módulo de ramo (ex.: carros no pátio) */
    public function resumo(): JsonResponse
    {
        $caixa = Caixa::where('situacao', 'aberto')->orderBy('id')->first();

        return response()->json(Modulos::pdvInicio() + [
            'caixa' => $caixa ? ['desde' => $caixa->aberto_em->format('H:i')] : null,
        ]);
    }
}
