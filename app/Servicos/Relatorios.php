<?php

namespace App\Servicos;

use App\Models\OrdemServico;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de dinheiro usadas por mais de uma tela (Início, Contas em aberto, Relatórios). Uma regra, um lugar:
 * as telas só chamam daqui. Todas filtram pela empresa atual (consulta direta ao banco não passa pelo escopo global).
 */
class Relatorios
{
    /**
     * Uma linha por cliente com contas em aberto: total (créditos abatem), quanto já venceu,
     * vencimento mais antigo e o resumo dos itens. Mesma regra do Início e do relatório do sistema atual.
     */
    public static function abertasPorCliente(?string $busca = null): \Illuminate\Support\Collection
    {
        $hoje = now()->toDateString();
        $sinal = OrdemServico::SQL_VALOR;
        $q = DB::table('ordens_servico as o')->join('clientes as c', 'c.id', '=', 'o.cliente_id')
            ->where('o.empresa_id', app(\App\Support\EmpresaAtual::class)->id())
            ->where('o.situacao', 'aberta')
            ->when($busca, fn ($q) => $q->where(fn ($w) => $w->where('c.nome', 'like', '%'.$busca.'%')->orWhere('o.placa', 'like', '%'.\App\Support\Placa::normalizar($busca).'%')->when(ctype_digit($busca), fn ($w) => $w->orWhere('c.id', (int) $busca))))
            ->groupBy('c.id', 'c.nome', 'c.celular', 'c.telefone', 'c.ativo')
            ->havingRaw("SUM({$sinal}) > 0")
            ->selectRaw("c.id, c.nome, c.ativo, COALESCE(c.celular, c.telefone) AS contato,
                SUM({$sinal}) AS total,
                SUM(o.tipo <> 'credito') AS qtd,
                SUM(o.tipo = 'mensalidade') AS n_mensalidade,
                SUM(o.tipo = 'servico') AS n_servico,
                SUM(o.tipo = 'estadia') AS n_estadia,
                SUM(o.tipo = 'debito') AS n_debito,
                SUM(o.tipo = 'credito') AS n_credito,
                SUM(CASE WHEN o.tipo <> 'credito' AND COALESCE(o.vencimento, o.data) < ? THEN o.valor ELSE 0 END) AS vencido,
                MIN(CASE WHEN o.tipo <> 'credito' THEN COALESCE(o.vencimento, o.data) END) AS mais_antigo,
                GROUP_CONCAT(DISTINCT o.placa ORDER BY o.placa SEPARATOR ' · ') AS placas", [$hoje]);

        return $q->get()->map(function ($l) use ($hoje) {
            $total = round((float) $l->total, 2);
            $vencido = round(min((float) $l->vencido, $total), 2);   // crédito também abate do vencido
            $p = [];
            foreach ([['n_mensalidade', 'mensalidade', 'mensalidades'], ['n_servico', 'lavagem/serviço', 'lavagens/serviços'], ['n_estadia', 'estadia', 'estadias'], ['n_debito', 'débito', 'débitos']] as [$k, $um, $varios]) {
                if ($l->$k > 0) {
                    $p[] = $l->$k.' '.($l->$k == 1 ? $um : $varios);
                }
            }
            if ($l->n_credito > 0) {
                $p[] = 'crédito abatido';
            }

            return [
                'id' => $l->id,
                'nome' => $l->nome,
                'ativo' => (bool) $l->ativo,
                'contato' => $l->contato,
                'total' => $total,
                'vencido' => $vencido,
                'itens' => implode(' · ', $p),
                'qtd' => (int) $l->n_mensalidade + (int) $l->n_servico + (int) $l->n_estadia + (int) $l->n_debito,
                'placas' => collect(explode(' · ', (string) $l->placas))->filter()->map(fn ($p) => \App\Support\Placa::formatar($p))->implode(' · '),
                'mais_antigo' => $l->mais_antigo,
                'dias' => $vencido > 0 && $l->mais_antigo ? (int) \Carbon\Carbon::parse($l->mais_antigo)->diffInDays($hoje) : 0,
            ];
        });
    }

    /** soma do que foi baixado no período, por tipo de ordem (crédito usado entra negativo) */
    public static function porCategoria(string $de, string $ate): array
    {
        $linhas = DB::table('pagamento_itens as i')
            ->join('pagamentos as p', 'p.id', '=', 'i.pagamento_id')
            ->join('ordens_servico as o', 'o.id', '=', 'i.ordem_id')
            ->where('p.empresa_id', app(\App\Support\EmpresaAtual::class)->id())
            ->where('p.situacao', 'confirmado')
            ->whereBetween('p.recebido_em', [$de.' 00:00:00', $ate.' 23:59:59'])
            ->groupBy('o.tipo')->selectRaw('o.tipo, SUM(i.valor) AS total, COUNT(*) AS qtd')->get();

        return $linhas->map(fn ($l) => ['tipo' => $l->tipo, 'rotulo' => OrdemServico::TIPOS[$l->tipo] ?? $l->tipo, 'total' => round((float) $l->total, 2), 'qtd' => (int) $l->qtd])->values()->all();
    }

    /** total baixado (soma dos itens dos pagamentos confirmados) e quantas contas, sem contar créditos */
    public static function baixado(string $de, string $ate): array
    {
        $l = \Illuminate\Support\Facades\DB::table('pagamento_itens as i')
            ->join('pagamentos as p', 'p.id', '=', 'i.pagamento_id')
            ->join('ordens_servico as o', 'o.id', '=', 'i.ordem_id')
            ->where('p.empresa_id', app(\App\Support\EmpresaAtual::class)->id())
            ->where('p.situacao', 'confirmado')
            ->whereBetween('p.recebido_em', [$de.' 00:00:00', $ate.' 23:59:59'])
            ->selectRaw("COALESCE(SUM(i.valor), 0) AS total, SUM(o.tipo <> 'credito') AS contas")->first();

        return ['total' => round((float) $l->total, 2), 'contas' => (int) $l->contas];
    }
}
