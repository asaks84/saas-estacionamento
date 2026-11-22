<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retirada do sócio registrada na gestão deixa de ser apagada (22/11/2026): passa a ser cancelada com motivo,
 * data e usuário, como as despesas e os pagamentos. A cancelada fica guardada e sai do Resultado e do fluxo de caixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixa_movimentos', function (Blueprint $t) {
            $t->timestamp('cancelada_em')->nullable()->after('aparelho_id');
            $t->foreignId('cancelada_por')->nullable()->after('cancelada_em')->constrained('usuarios');
            $t->string('motivo_cancelamento', 200)->nullable()->after('cancelada_por');
        });
    }

    public function down(): void
    {
        Schema::table('caixa_movimentos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cancelada_por');
            $t->dropColumn(['cancelada_em', 'motivo_cancelamento']);
        });
    }
};
