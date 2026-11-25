<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use App\Modulos\Estacionamento\Models\Estadia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ação do PDV com código único (8.7). Sem internet, o celular guarda a ação numa fila e manda depois; o código
 * impede lançar duas vezes. O que não fechou na volta fica "pendente" até o gerente resolver.
 * Os tipos de ação (e o nome de cada um) vêm dos módulos de ramo: App\Servicos\Pdv\SemInternet::rotulo().
 */
class PdvAcao extends Modelo
{
    use PertenceAEmpresa;

    protected $table = 'pdv_acoes';

    protected $attributes = ['situacao' => 'ok', 'sem_internet' => false];

    protected function casts(): array
    {
        return ['dados' => 'array', 'feita_em' => 'datetime', 'resolvida_em' => 'datetime', 'sem_internet' => 'boolean'];
    }

    public function aparelho(): BelongsTo
    {
        return $this->belongsTo(Aparelho::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** estadia da ação (módulo Estacionamento; coluna estadia_id da tabela, ver docs/saas/04 item 5) */
    public function estadia(): BelongsTo
    {
        return $this->belongsTo(Estadia::class);
    }

    public function resolvidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resolvida_por');
    }
}
