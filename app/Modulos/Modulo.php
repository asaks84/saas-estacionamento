<?php

namespace App\Modulos;

use App\Models\Cliente;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base de um módulo de ramo (Estacionamento, Agenda, Locação…). Ver docs/saas/04-multiplos-produtos.md.
 *
 * Cada módulo mora em app/Modulos/<Nome> e traz, se tiver:
 * - rotas.php (telas da gestão, carregadas com o grupo "web") e rotas-api.php (prefixo /api, grupo "api");
 * - permissoes.php (mesmo formato de config/permissoes.php; os itens entram nos grupos de lá);
 * - migrations/ (tabelas novas do módulo);
 * - telas em resources/js/Modulos/<Nome>/Pages (nome da tela: "<Nome>/Pages/...").
 *
 * O núcleo nunca chama uma classe de módulo direto: chama App\Modulos\Modulos, que pergunta a cada módulo
 * pelos métodos abaixo (pontos de extensão). Módulo que não usa um ponto não precisa sobrescrever.
 */
abstract class Modulo extends ServiceProvider
{
    /** chave curta do módulo (ex.: "estacionamento") */
    abstract public function chave(): string;

    /** nome que aparece para as pessoas */
    abstract public function nome(): string;

    /** pasta do módulo (app/Modulos/<Nome>) */
    protected function pasta(): string
    {
        return dirname((new \ReflectionClass($this))->getFileName());
    }

    public function register(): void
    {
        // permissões do módulo entram nos grupos do catálogo (config/permissoes.php)
        $arquivo = $this->pasta().'/permissoes.php';
        if (is_file($arquivo)) {
            $catalogo = config('permissoes', []);
            foreach (require $arquivo as $grupo => $itens) {
                $catalogo[$grupo] = ($catalogo[$grupo] ?? []) + $itens;
            }
            config(['permissoes' => $catalogo]);
        }
    }

    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            if (is_file($r = $this->pasta().'/rotas.php')) {
                Route::middleware('web')->group($r);
            }
            if (is_file($r = $this->pasta().'/rotas-api.php')) {
                Route::prefix('api')->middleware('api')->group($r);
            }
        }
        if (is_dir($m = $this->pasta().'/migrations')) {
            $this->loadMigrationsFrom($m);
        }
    }

    // ---- pontos de extensão (o núcleo chama por App\Modulos\Modulos) ----

    /** LGPD: motivos que impedem anonimizar o cliente agora (lista de frases) */
    public function bloqueiosAnonimizar(Cliente $cliente): array
    {
        return [];
    }

    /** LGPD: seções a mais na exportação dos dados do cliente (chave => linhas) */
    public function dadosPessoais(Cliente $cliente): array
    {
        return [];
    }

    /** LGPD: apaga o que identifica o cliente nas tabelas do módulo (roda dentro da transação da anonimização) */
    public function anonimizar(Cliente $cliente, Collection $veiculoIds): void {}

    /** Início: dados a mais para a tela inicial (chave => valor) */
    public function inicio(Usuario $usuario): array
    {
        return [];
    }

    /** dados compartilhados com todas as telas (chave => função), como contadores do menu */
    public function compartilhado(?Usuario $usuario): array
    {
        return [];
    }
}
