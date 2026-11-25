<?php

// Módulos de ramo instalados (docs/saas/04-multiplos-produtos.md). Cada um é um App\Modulos\Modulo,
// com rotas, permissões, telas e pontos de extensão próprios. O núcleo (clientes, dinheiro, cobrança,
// financeiro, NFS-e, WhatsApp, PDV base) fica fora daqui e vale para todos.
// Ligar/desligar por empresa entra com o painel da plataforma (Fase 2 do plano); por enquanto,
// todo módulo listado vale para todas as empresas.
return [
    App\Modulos\Estacionamento\EstacionamentoServiceProvider::class,
];
