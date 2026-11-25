<?php

use App\Http\Controllers\Pdv\CaixaPdvController;
use App\Http\Controllers\Pdv\PdvController;
use App\Http\Controllers\Pdv\PixPdvController;
use Illuminate\Support\Facades\Route;

/*
 * API do PDV (docs/15-plano-pdv.md). Sem sessão nem cookie: o aparelho se identifica pelo token
 * (middleware "aparelho") e cada ação pede o PIN de quem está atendendo.
 * A API das telas de cada módulo de ramo fica em app/Modulos/<Nome>/rotas-api.php (ex.: estadias e lavagens).
 */
Route::prefix('pdv')->middleware('publica')->group(function () {
    Route::post('/parear', [PdvController::class, 'parear'])->middleware('throttle:10,1,pdv-parear');

    Route::middleware(['aparelho', 'throttle:120,1,pdv'])->group(function () {
        Route::get('/eu', [PdvController::class, 'eu']);
        Route::post('/pin/conferir', [PdvController::class, 'conferirPin'])->middleware('throttle:30,1,pdv-pin');
        Route::post('/pin/cadastrar', [PdvController::class, 'cadastrarPin'])->middleware('throttle:20,1,pdv-cadastro');
        // caixa (8.3)
        Route::get('/caixa', [CaixaPdvController::class, 'estado']);
        Route::get('/caixa/opcoes', [CaixaPdvController::class, 'opcoes']);
        Route::get('/clientes/{id}/contas', [CaixaPdvController::class, 'contas'])->whereNumber('id');
        // Pix com QR (8.5): a tela consulta a cada 3 segundos até pagar
        Route::get('/pix/{id}', [PixPdvController::class, 'ver'])->whereNumber('id');
        Route::middleware('throttle:60,1,pdv-acao')->group(function () {
            Route::post('/pix', [PixPdvController::class, 'criar']);
            Route::post('/pix/{id}/cancelar', [PixPdvController::class, 'cancelar'])->whereNumber('id');
            Route::post('/caixa/resumo', [CaixaPdvController::class, 'resumo']);
            Route::post('/caixa/movimentos', [CaixaPdvController::class, 'movimentar']);
            Route::post('/caixa/fechar', [CaixaPdvController::class, 'fechar']);
            Route::post('/clientes/{id}/receber', [CaixaPdvController::class, 'receber'])->whereNumber('id');
        });
    });
});
