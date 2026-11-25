<?php

namespace App\Modulos;

use App\Models\Cliente;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Os módulos de ramo ligados no sistema (config/modulos.php) e os pontos de extensão que o núcleo usa.
 * Ver App\Modulos\Modulo e docs/saas/04-multiplos-produtos.md.
 */
class Modulos
{
    /** @return Modulo[] */
    public static function todos(): array
    {
        return array_values(app()->getProviders(Modulo::class));
    }

    public static function chaves(): array
    {
        return array_map(fn (Modulo $m) => $m->chave(), self::todos());
    }

    public static function bloqueiosAnonimizar(Cliente $cliente): array
    {
        return array_merge([], ...array_map(fn (Modulo $m) => $m->bloqueiosAnonimizar($cliente), self::todos()));
    }

    public static function dadosPessoais(Cliente $cliente): array
    {
        return array_merge([], ...array_map(fn (Modulo $m) => $m->dadosPessoais($cliente), self::todos()));
    }

    public static function anonimizar(Cliente $cliente, Collection $veiculoIds): void
    {
        foreach (self::todos() as $m) {
            $m->anonimizar($cliente, $veiculoIds);
        }
    }

    public static function inicio(Usuario $usuario): array
    {
        return array_merge([], ...array_map(fn (Modulo $m) => $m->inicio($usuario), self::todos()));
    }

    public static function compartilhado(?Usuario $usuario): array
    {
        return array_merge([], ...array_map(fn (Modulo $m) => $m->compartilhado($usuario), self::todos()));
    }
}
