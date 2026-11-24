<?php

namespace App\Http\Controllers;

use App\Models\Anotacao;
use App\Servicos\Despesas;
use App\Servicos\Relatorios;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Início: números do dia a dia, cada um só para quem tem a permissão. */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $u = $request->user();
        $aberto = null;
        if ($u->pode('contas_aberto')) {
            $linhas = Relatorios::abertasPorCliente();
            $aberto = [
                'clientes' => $linhas->count(),
                'total' => round($linhas->sum('total'), 2),
                'vencido' => round($linhas->sum('vencido'), 2),
                'inadimplentes' => $linhas->where('vencido', '>', 0)->count(),
            ];
        }
        $recebido = null;
        if ($u->pode('faturamento')) {
            // mesma conta do Início do sistema atual: soma das contas baixadas no período
            // (crédito usado abate). O dinheiro por forma de pagamento fica em Recebimentos.
            $ini = now()->startOfMonth();
            $mes = Relatorios::baixado($ini->toDateString(), now()->toDateString());
            $recebido = [
                'mes' => now()->format('Y-m'),
                'total' => $mes['total'],
                'qtd' => $mes['contas'],
                'hoje' => Relatorios::baixado(now()->toDateString(), now()->toDateString())['total'],
                'por_categoria' => Relatorios::porCategoria($ini->toDateString(), now()->toDateString()),
            ];
        }

        return Inertia::render('Inicio', ['aberto' => $aberto, 'recebido' => $recebido, 'anotacoes' => Anotacao::doUsuario((int) $request->user()->id),
            'pagar' => $u->pode('contas_pagar') ? Despesas::resumo() : null,
            // certificado da integração do Inter vencendo (boleto, baixa automática e extrato param juntos)
            'inter_vence' => $u->pode('configuracoes') ? self::interVence() : null,
            // convênios com fechamento hoje (ou atrasado): botão "Gerar cobrança"
            'convenios_fechar' => $u->pode('convenios') ? \App\Servicos\Convenios::aFechar()->map(fn ($x) => ['id' => $x['convenio']->id,
                'nome' => $x['convenio']->cliente?->nome, 'dia' => $x['dia']->toDateString(), 'itens' => $x['itens'], 'total' => $x['total'],
                'fechamento' => $x['convenio']->resumoFechamento()])->values() : []]);
    }

    private static function interVence(): ?array
    {
        $i = \App\Models\Integracao::inter();
        if (! $i || ! $i->ativo || ! $i->certificado_validade) {
            return null;
        }
        $dias = (int) today()->diffInDays($i->certificado_validade, false);

        return $dias <= 30 ? ['data' => $i->certificado_validade->format('Y-m-d'), 'dias' => $dias] : null;
    }
}
