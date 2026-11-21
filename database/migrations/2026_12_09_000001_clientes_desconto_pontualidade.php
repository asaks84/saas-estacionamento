<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desconto de pontualidade do cliente (21/11/2026): valor total em R$ por mês, digitado pelo usuário (soma de todas
 * as mensalidades dele). Vazio = segue a regra da empresa (Configurações › Cobranças); 0 = sem desconto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->decimal('desconto_pontualidade', 10, 2)->nullable()->after('dia_vencimento');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->dropColumn('desconto_pontualidade');
        });
    }
};
