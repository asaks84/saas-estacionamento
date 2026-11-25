<?php

use App\Modulos\Estacionamento\Http\Controllers\Pdv\EstadiaPdvController;
use App\Modulos\Estacionamento\Http\Controllers\Pdv\InicioPdvController;
use App\Modulos\Estacionamento\Http\Controllers\Pdv\LavagemPdvController;
use App\Modulos\Estacionamento\Http\Controllers\Pdv\SemInternetController;
use Illuminate\Support\Facades\Route;

/*
 * API do PDV do módulo Estacionamento (docs/15-plano-pdv.md), carregada com prefixo /api por App\Modulos\Modulo.
 * Mesmas proteções de routes/api.php: o aparelho se identifica pelo token (middleware "aparelho")
 * e cada ação pede o PIN de quem está atendendo.
 */
Route::prefix('pdv')->middleware(['publica', 'aparelho', 'throttle:120,1,pdv'])->group(function () {
    Route::get('/inicio', [InicioPdvController::class, 'resumo']);
    Route::get('/ticket', [InicioPdvController::class, 'ticket']);
    // modo sem internet (8.7): a cópia guardada no celular e a fila que sobe quando a internet volta
    Route::get('/pacote', [SemInternetController::class, 'pacote']);
    Route::post('/sincronizar', [SemInternetController::class, 'sincronizar'])->middleware('throttle:30,1,pdv-fila');

    // estadias (8.2)
    Route::get('/estadias/opcoes', [EstadiaPdvController::class, 'opcoes']);
    Route::get('/estadias/patio', [EstadiaPdvController::class, 'patio']);
    Route::get('/estadias/placa/{placa}', [EstadiaPdvController::class, 'placa']);
    Route::get('/estadias/buscar', [EstadiaPdvController::class, 'buscar']);
    Route::get('/estadias/{id}/calculo', [EstadiaPdvController::class, 'calculo'])->whereNumber('id');
    Route::get('/clientes', [EstadiaPdvController::class, 'clientes']);
    // lavagens (8.4)
    Route::get('/lavagens/opcoes', [LavagemPdvController::class, 'opcoes']);
    Route::get('/lavagens/andamento', [LavagemPdvController::class, 'andamento']);
    Route::get('/lavagens/placa/{placa}', [LavagemPdvController::class, 'placa']);
    Route::get('/lavagens/buscar', [LavagemPdvController::class, 'buscar']);
    Route::get('/lavagens/{id}/calculo', [LavagemPdvController::class, 'calculo'])->whereNumber('id');

    Route::middleware('throttle:60,1,pdv-acao')->group(function () {
        Route::post('/lavagens', [LavagemPdvController::class, 'vender']);
        Route::post('/lavagens/{id}/etapa', [LavagemPdvController::class, 'etapa'])->whereNumber('id');
        Route::post('/lavagens/{id}/entregar', [LavagemPdvController::class, 'entregar'])->whereNumber('id');
        Route::post('/estadias', [EstadiaPdvController::class, 'entrar']);
        Route::post('/estadias/{id}/saida', [EstadiaPdvController::class, 'sair'])->whereNumber('id');
        Route::post('/estadias/{id}/cancelar', [EstadiaPdvController::class, 'cancelar'])->whereNumber('id');
    });
});
