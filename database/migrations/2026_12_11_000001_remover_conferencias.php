<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Virada (24/11/2026): o ERP passou a ser o oficial e o espelho do sistema atual foi desligado.
// A tabela das conferências do paralelo (resultado de cada importação + comparação) sai junto da tela.
// Não guarda dinheiro nem dado de cliente: só os totais comparados de cada hora.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('conferencias');
    }

    public function down(): void
    {
        Schema::create('conferencias', function (Blueprint $t) {
            $t->id();
            $t->dateTime('executada_em');
            $t->string('situacao', 10);
            $t->unsignedSmallInteger('diferencas')->default(0);
            $t->json('linhas')->nullable();
            $t->text('mensagem')->nullable();
            $t->unsignedInteger('duracao_seg')->nullable();
            $t->index('executada_em');
        });
    }
};
