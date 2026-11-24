<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Support\EmpresaAtual;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BancoLegado;
use Tests\TestCase;

/** Backup dos bancos e agendamento (o espelho do paralelo saiu na virada). */
class ParaleloTest extends TestCase
{
    use BancoLegado;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarBancoLegado();
        DB::connection('legado')->table('USUARIOS')->insert([
            'usr_id' => 1, 'usr_full_name' => 'CAUE', 'usr_nickname' => 'caue', 'usr_password' => password_hash('senha-boa', PASSWORD_BCRYPT), 'usr_admin' => 1,
        ]);
    }

    public function test_backup_dos_bancos(): void
    {
        Storage::fake('local');
        $a = $this->criarEmpresa('a');
        $this->criarCliente($a);
        $velho = 'backups/erp-2020-01-01-0310.sql.gz';
        Storage::disk('local')->put($velho, 'x');
        touch(Storage::disk('local')->path($velho), now()->subDays(30)->getTimestamp());

        // em PHP puro (a hospedagem não deixa rodar mysqldump)
        $this->artisan('backup:banco')->assertSuccessful();
        $arquivos = collect(Storage::disk('local')->files('backups'));
        $this->assertCount(2, $arquivos);   // erp + sistema atual; o de 30 dias saiu
        $this->assertNotContains($velho, $arquivos);
        $sql = gzdecode(Storage::disk('local')->get($arquivos->first(fn ($f) => str_starts_with(basename($f), 'erp-'))));
        $this->assertStringContainsString('CREATE TABLE `empresas`', $sql);
        $this->assertStringContainsString("'Empresa a'", $sql);
        $this->assertMatchesRegularExpression('/INSERT INTO `veiculos` \(([^)]*)\) VALUES/', $sql);
        preg_match('/INSERT INTO `veiculos` \(([^)]*)\) VALUES/', $sql, $m);
        $this->assertStringNotContainsString('placa_ativa', $m[1]);          // coluna gerada fica fora do INSERT

        // banco inacessível: comando falha e não deixa arquivo
        config(['database.connections.legado.database' => 'banco_que_nao_existe']);
        \DB::purge('legado');
        $this->artisan('backup:banco')->assertFailed();
        $this->assertCount(0, collect(Storage::disk('local')->files('backups'))->filter(fn ($f) => str_contains($f, 'sistema-atual') && filesize(Storage::disk('local')->path($f)) === 0));
    }

    public function test_agendamento(): void
    {
        // tarefas rodam no mesmo processo (sem proc_open): identificadas pelo nome
        $eventos = collect(app(Schedule::class)->events())->map(fn ($e) => $e->description);
        $this->assertFalse($eventos->contains(fn ($c) => str_contains($c, 'legado:espelhar')));   // espelho desligado na virada
        $this->assertTrue($eventos->contains(fn ($c) => str_contains($c, 'backup:banco')));
    }
}
