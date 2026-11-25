<?php

namespace App\Servicos\Pdv;

use App\Models\Aparelho;
use App\Models\Caixa;
use App\Models\Concerns\EmpresaScope;
use App\Models\PdvAcao;
use App\Models\Usuario;
use App\Modulos\Modulos;
use App\Support\Atividades;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Modo sem internet do PDV (parte 8.7, docs/15-plano-pdv.md seção 8i): a base comum a todos os ramos.
 *
 * - pacote(): a cópia que o celular guarda para trabalhar sem internet. Aqui, o que é de todos (quem tem PIN, caixa,
 *   pendências); cada módulo de ramo soma o seu (Modulo::pdvPacote; no Estacionamento: tabelas, pátio, mensalistas).
 * - sincronizar(): as ações feitas sem internet sobem na ordem em que aconteceram, cada uma com um código único
 *   (uuid) que impede lançar duas vezes. Cada ação roda "na hora do celular". Quem lança cada tipo de ação é o
 *   módulo que a declarou (Modulo::pdvAcoes). O que não fecha vira pendência para o gerente; a fila nunca trava.
 */
class SemInternet
{
    /** mais antigo que isso não entra (celular esquecido sem internet por semanas: melhor resolver na mão) */
    public const DIAS_MAX = 7;

    /** tudo o que o celular precisa para trabalhar sem internet */
    public static function pacote(Aparelho $a): array
    {
        // quem tem PIN nesta unidade (ou em todas), com a dica do PIN (Pins::dica): o celular pega erro de digitação e mostra
        // o nome, sem receber nada que permita descobrir o PIN. Quem confere de verdade é o servidor, na sincronização.
        // Quem ainda não tem a versão sem internet ganha no próximo uso do PIN com internet.
        $usuarios = Usuario::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $a->empresa_id)->where('ativo', true)
            ->whereNotNull('pin_hash')->when($a->unidade_id, fn ($q) => $q->where(fn ($w) => $w->whereNull('unidade_id')->orWhere('unidade_id', $a->unidade_id)))
            ->with('nivel')->get()
            ->map(fn (Usuario $u) => ['id' => $u->id, 'nome' => $u->nome, 'pin_desde' => $u->pin_definido_em ? Carbon::parse($u->pin_definido_em)->toIso8601String() : null,
                'gerente' => $u->pode('pdv_gerente'), 'nivel' => $u->nivel?->nome, 'dica' => Pins::dica($u)])->values();

        $caixa = Caixa::where('situacao', 'aberto')->orderBy('id')->first();

        return [
            'gerado_em' => now()->toIso8601String(),
            'usuarios' => $usuarios,
            'pin_sal' => Pins::salOffline($a->empresa_id),
            'pin_rodadas' => Pins::OFFLINE_RODADAS,
            'pin_chave' => Pins::chavePublica((int) $a->empresa_id),
            'caixa' => $caixa ? ['desde' => $caixa->aberto_em->format('H:i')] : null,
            'pendentes' => PdvAcao::where('aparelho_id', $a->id)->where('situacao', 'pendente')->count(),
        ] + Modulos::pdvPacote($a);
    }

    /** nome de um tipo de ação (vem do módulo que a declarou) */
    public static function rotulo(string $tipo): string
    {
        return Modulos::pdvAcoes()[$tipo]['rotulo'] ?? $tipo;
    }

    /**
     * Sobe as ações da fila, na ordem. Devolve, para cada uuid, ok ou pendente (com a mensagem). Ação já recebida
     * (mesmo uuid) responde de novo o mesmo resultado, sem refazer.
     *
     * @param  array<int, array{uuid: string, tipo: string, feita_em: string, usuario_id: ?int, prova: ?string, dados: array}>  $acoes
     */
    public static function sincronizar(Aparelho $a, array $acoes): array
    {
        $saida = [];
        foreach ($acoes as $x) {
            $uuid = mb_strtolower((string) ($x['uuid'] ?? ''));
            if (! Str::isUuid($uuid)) {
                continue;
            }
            $ja = PdvAcao::where('uuid', $uuid)->first();
            if ($ja) {
                $saida[] = self::resposta($ja);

                continue;
            }
            $saida[] = self::resposta(self::executar($a, $uuid, $x));
        }

        return $saida;
    }

    private static function resposta(PdvAcao $p): array
    {
        return ['uuid' => $p->uuid, 'situacao' => $p->situacao === 'ok' ? 'ok' : 'pendente', 'mensagem' => $p->mensagem];
    }

    private static function executar(Aparelho $a, string $uuid, array $x): PdvAcao
    {
        $tipo = (string) ($x['tipo'] ?? '');
        $tipos = Modulos::pdvAcoes();
        $dados = is_array($x['dados'] ?? null) ? $x['dados'] : [];
        $quando = self::quando($x['feita_em'] ?? null);
        // quem fez sai do PIN que o celular trancou com a chave do servidor, nunca do usuario_id que o celular informa
        $u = Pins::abrirProva((int) $a->empresa_id, isset($x['prova']) ? (string) $x['prova'] : null, $uuid);
        $acao = new PdvAcao(['aparelho_id' => $a->id, 'uuid' => $uuid, 'tipo' => mb_substr($tipo, 0, 30), 'dados' => $dados,
            'usuario_id' => $u instanceof Usuario ? $u->id : null, 'feita_em' => $quando, 'sem_internet' => true]);

        $problema = match (true) {
            ! isset($tipos[$tipo]) => 'Ação desconhecida ('.$tipo.'). Atualize o app do PDV.',
            ! $quando => 'Hora da ação inválida.',
            $quando->isAfter(now()->addMinutes(10)) => 'A hora do celular estava no futuro ('.$quando->format('d/m H:i').'). Confira a data e hora do celular.',
            $quando->lt(now()->subDays(self::DIAS_MAX)) => 'Ação feita há mais de '.self::DIAS_MAX.' dias ('.$quando->format('d/m H:i').'): confira e lance na gestão.',
            is_string($u) => $u,
            default => null,
        };
        if ($problema) {
            return self::pendente($acao, $problema);
        }
        $def = $tipos[$tipo];

        Auth::setUser($u);
        // tudo roda na hora em que aconteceu no celular (entrada, saída, recebimento, registro de atividades)
        $agoraReal = now();
        $relogio = Carbon::getTestNow(); // nos testes já há um relógio fixo: volta para ele
        Carbon::setTestNow($quando);
        try {
            DB::transaction(function () use ($acao, $def, $dados) {
                ($def['executar'])($acao, $dados);
                $acao->save();
            });
        } catch (ValidationException $e) {
            Carbon::setTestNow($relogio);

            return self::pendente($acao, collect($e->errors())->flatten()->first() ?: 'Não deu certo.');
        } catch (\Throwable $e) {
            Carbon::setTestNow($relogio);
            report($e);

            return self::pendente($acao, 'Erro ao lançar: '.mb_substr($e->getMessage(), 0, 200));
        } finally {
            Carbon::setTestNow($relogio);
        }
        $acao->forceFill(['criado_em' => $agoraReal, 'atualizado_em' => $agoraReal])->saveQuietly();
        // o módulo pode pedir conferência mesmo lançada (ex.: saída que chegou muito depois de feita)
        if (isset($def['conferir']) && ($aviso = ($def['conferir'])($acao, $quando, $agoraReal, $u))) {
            self::pendente($acao, $acao->situacao === 'pendente' && $acao->mensagem ? $acao->mensagem.' '.$aviso : $aviso);
        }

        return $acao;
    }

    private static function pendente(PdvAcao $acao, string $mensagem): PdvAcao
    {
        $acao->forceFill(['situacao' => 'pendente', 'mensagem' => mb_substr($mensagem, 0, 500), 'estadia_id' => $acao->estadia_id])->save();
        Atividades::registrar('pdv_pendencia', self::rotulo($acao->tipo).' feita sem internet ficou pendente: '.$mensagem);

        return $acao;
    }

    private static function quando(?string $q): ?Carbon
    {
        try {
            return $q ? Carbon::parse($q)->setTimezone(config('app.timezone'))->seconds(0) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * registrada na hora (com internet), com o código da ação: se o celular mandar de novo pela fila, não duplica.
     * $campos: vínculos da ação (ex.: ['estadia_id' => 10]).
     */
    public static function registrarNaHora(?string $uuid, string $tipo, array $campos = []): void
    {
        if (! $uuid || ! Str::isUuid($uuid) || PdvAcao::where('uuid', mb_strtolower($uuid))->exists()) {
            return;
        }
        PdvAcao::create(['aparelho_id' => request()?->attributes->get('aparelho')?->id, 'uuid' => mb_strtolower($uuid), 'tipo' => $tipo,
            'usuario_id' => Auth::id(), 'feita_em' => now()] + $campos);
    }

    /** a pendência foi resolvida na gestão (com o que foi feito) */
    public static function resolver(PdvAcao $p, string $observacao): void
    {
        $p->update(['situacao' => 'resolvida', 'observacao' => mb_substr($observacao, 0, 300), 'resolvida_por' => Auth::id(), 'resolvida_em' => now()]);
        Atividades::registrar('pdv_pendencia_resolvida', self::rotulo($p->tipo).' sem internet: '.$observacao);
    }
}
