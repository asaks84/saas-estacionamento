<?php

// Permissões do módulo Estacionamento. Mesmo formato de config/permissoes.php: os itens entram
// nos grupos de lá (App\Modulos\Modulo::register). Ver docs/08-seguranca-permissoes-lgpd.md.
return [
    'Operação' => [
        'patio_corrigir' => ['Corrigir no pátio', 'Corrigir placa, modelo e tamanho, cancelar e encerrar entradas esquecidas'],
    ],
    'Gestão' => [
        'convenios' => ['Configurar convênios', null],
    ],
];
