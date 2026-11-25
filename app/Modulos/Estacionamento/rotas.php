<?php

use App\Modulos\Estacionamento\Http\Controllers\ConvenioController;
use App\Modulos\Estacionamento\Http\Controllers\EstadiaController;
use App\Modulos\Estacionamento\Http\Controllers\EtapaLavagemController;
use App\Modulos\Estacionamento\Http\Controllers\LavagemController;
use App\Modulos\Estacionamento\Http\Controllers\PatioController;
use App\Modulos\Estacionamento\Http\Controllers\TarifaController;
use Illuminate\Support\Facades\Route;

// Rotas da gestão do módulo Estacionamento (carregadas com o grupo "web" por App\Modulos\Modulo).
// Mesma regra de routes/web.php: toda rota declara a permissão que exige ("permissao:<chave>" ou "permissao:livre").

Route::middleware('auth')->group(function () {
    Route::middleware('permissao:livre')->group(function () {
        // operação: pátio unificado (estadias + lavagens). Entrada e saída são do PDV; as rotas de gravação
        // de estadia e lavagem abaixo ficam como contingência (sem tela na gestão) até o modo sem internet (8.7)
        Route::get('/patio', [PatioController::class, 'index']);
        Route::get('/patio/historico', [PatioController::class, 'historico']);
        Route::get('/patio/pendencias', [PatioController::class, 'pendencias']);
        // adaptação sem o PDV: estadia avulsa (cliente e valor) na conta do cliente, como o "Lançar" do cliente
        Route::post('/patio/estadia-avulsa', [PatioController::class, 'estadiaAvulsa']);
        Route::redirect('/estadias', '/patio');
        Route::redirect('/estadias/historico', '/patio/historico');
        Route::get('/estadias/placa', [EstadiaController::class, 'placa']);
        Route::post('/estadias', [EstadiaController::class, 'store']);
        Route::get('/estadias/{estadia}/calcular', [EstadiaController::class, 'calcular']);
        Route::post('/estadias/{estadia}/saida', [EstadiaController::class, 'saida']);
        Route::post('/estadias/{estadia}/cancelar', [EstadiaController::class, 'cancelar']);
        Route::redirect('/lavagens', '/patio');
        Route::redirect('/lavagens/nova', '/patio');
        Route::get('/lavagens/placa', [LavagemController::class, 'placa']);
        Route::post('/lavagens', [LavagemController::class, 'store']);
        Route::post('/lavagens/{ordem}/etapa', [LavagemController::class, 'etapa']);
        Route::post('/lavagens/{ordem}/receber', [LavagemController::class, 'receber']);
    });

    Route::middleware('permissao:patio_corrigir')->group(function () {
        Route::post('/patio/estadias/{estadia}/corrigir', [PatioController::class, 'corrigirEstadia']);
        Route::post('/patio/estadias/{estadia}/cancelar', [PatioController::class, 'cancelarEstadia']);
        Route::post('/patio/estadias/{estadia}/encerrar', [PatioController::class, 'encerrarEstadia']);
        Route::post('/patio/lavagens/{ordem}/corrigir', [PatioController::class, 'corrigirLavagem']);
        Route::post('/patio/lavagens/{ordem}/cancelar', [PatioController::class, 'cancelarLavagem']);
        Route::post('/patio/lavagens/{ordem}/entregar', [PatioController::class, 'entregarLavagem']);
        // pendências do modo sem internet do PDV (8.7)
        Route::post('/patio/pendencias/{acao}/resolver', [PatioController::class, 'resolverPendencia']);
    });

    Route::middleware('permissao:convenios')->group(function () {
        Route::get('/convenios', [ConvenioController::class, 'index']);
        Route::get('/convenios/novo', [ConvenioController::class, 'create']);
        Route::post('/convenios', [ConvenioController::class, 'store']);
        Route::get('/convenios/{convenio}', [ConvenioController::class, 'edit']);
        Route::put('/convenios/{convenio}', [ConvenioController::class, 'update']);
        Route::post('/convenios/{convenio}/fechar', [ConvenioController::class, 'fechar']);
    });

    Route::middleware('permissao:configuracoes')->group(function () {
        Route::get('/configuracoes/etapas', [EtapaLavagemController::class, 'index']);
        Route::post('/configuracoes/etapas', [EtapaLavagemController::class, 'store']);
        Route::post('/configuracoes/etapas/estadia', [EtapaLavagemController::class, 'estadia']);
        Route::put('/configuracoes/etapas/{etapa}', [EtapaLavagemController::class, 'update']);
        Route::post('/configuracoes/etapas/{etapa}/mover', [EtapaLavagemController::class, 'mover']);
        Route::delete('/configuracoes/etapas/{etapa}', [EtapaLavagemController::class, 'destroy']);

        Route::get('/configuracoes/tarifas', [TarifaController::class, 'index']);
        Route::get('/configuracoes/tarifas/nova', [TarifaController::class, 'create']);
        Route::post('/configuracoes/tarifas', [TarifaController::class, 'store']);
        Route::post('/configuracoes/tarifas/simular', [TarifaController::class, 'simular']);
        Route::get('/configuracoes/tarifas/{tarifa}', [TarifaController::class, 'edit']);
        Route::put('/configuracoes/tarifas/{tarifa}', [TarifaController::class, 'update']);
        Route::delete('/configuracoes/tarifas/{tarifa}', [TarifaController::class, 'destroy']);
    });
});
