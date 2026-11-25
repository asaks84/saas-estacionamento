<?php

namespace Tests\Modulos\Estacionamento;

use App\Modulos\Estacionamento\EstacionamentoServiceProvider;
use App\Modulos\Modulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** O módulo Estacionamento ligado ao núcleo: permissões, rotas, telas e dados compartilhados (docs/saas/04). */
class ModuloTest extends TestCase
{
    use RefreshDatabase;

    public function test_modulo_esta_ligado_e_soma_as_permissoes_nos_grupos_do_catalogo(): void
    {
        $this->assertSame(['estacionamento'], Modulos::chaves());
        $this->assertInstanceOf(EstacionamentoServiceProvider::class, Modulos::todos()[0]);
        $this->assertArrayHasKey('patio_corrigir', config('permissoes.Operação'));
        $this->assertArrayHasKey('convenios', config('permissoes.Gestão'));
        // as do núcleo continuam lá
        $this->assertArrayHasKey('desconto', config('permissoes.Operação'));
    }

    public function test_telas_do_modulo_abrem_e_recebem_a_lista_de_modulos(): void
    {
        $a = $this->criarEmpresa('a');
        $this->actingAs($a->admin)->get('/patio')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Estacionamento/Pages/Patio/Index')->where('modulos', ['estacionamento'])->has('pdv_pendencias'));
        $this->actingAs($a->admin)->get('/convenios')->assertOk()->assertInertia(fn ($p) => $p->component('Estacionamento/Pages/Convenios/Index'));
        $this->actingAs($a->admin)->get('/configuracoes/tarifas')->assertOk()->assertInertia(fn ($p) => $p->component('Estacionamento/Pages/Configuracoes/Tarifas'));
        $this->actingAs($a->admin)->get('/')->assertOk()->assertInertia(fn ($p) => $p->component('Inicio')->has('convenios_fechar'));
    }
}
