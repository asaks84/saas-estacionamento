<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\FormaPagamento;
use App\Models\OrdemServico;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Servicos\Baixa;
use App\Servicos\FormasPagamento;
use App\Servicos\Inter\Boletos;
use App\Servicos\Relatorios;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Contas em aberto (e inadimplência), receber contas do cliente e estorno de pagamento. */
class ContaController extends Controller
{
    public function abertas(Request $request): Response|StreamedResponse
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'ver' => ['nullable', 'in:todas,vencidas']]);
        $ver = $f['ver'] ?? 'todas';
        $linhas = Relatorios::abertasPorCliente($f['q'] ?? null);
        $resumo = ['clientes' => $linhas->count(), 'total' => round($linhas->sum('total'), 2), 'vencido' => round($linhas->sum('vencido'), 2), 'inadimplentes' => $linhas->where('vencido', '>', 0)->count()];
        if ($ver === 'vencidas') {
            $linhas = $linhas->where('vencido', '>', 0)->sortByDesc('vencido');
        } else {
            $linhas = $linhas->sortBy([['mais_antigo', 'asc'], ['nome', 'asc']]);
        }

        if ($request->boolean('csv')) {
            return Csv::baixar('contas-em-aberto-'.now()->format('Y-m-d').'.csv',
                ['Código', 'Cliente', 'Contato', 'Placas', 'Itens', 'Total', 'Vencido', 'Mais antigo', 'Dias em atraso'],
                $linhas->map(fn ($l) => [$l['id'], $l['nome'], $l['contato'], $l['placas'], $l['itens'], Csv::numero($l['total']), Csv::numero($l['vencido']),
                    $l['mais_antigo'] ? \Carbon\Carbon::parse($l['mais_antigo'])->format('d/m/Y') : '', $l['dias']]));
        }

        return Inertia::render('Contas/Abertas', ['linhas' => $linhas->values(), 'resumo' => $resumo, 'filtros' => ['q' => $f['q'] ?? '', 'ver' => $ver]]);
    }

    public function receber(Cliente $cliente): Response
    {
        FormasPagamento::garantirPadrao();

        return Inertia::render('Contas/Receber', [
            'cliente' => $cliente->only(['id', 'nome']),
            'ordens' => Baixa::abertas($cliente)->map(fn (OrdemServico $o) => [
                'id' => $o->id,
                'numero' => $o->numero,
                'tipo' => $o->tipo,
                'descricao' => $o->descricao,
                'competencia' => $o->competencia,
                'placa' => $o->placa ? \App\Support\Placa::formatar($o->placa) : null,
                'data' => $o->data->format('Y-m-d'),
                'vencimento' => $o->vencimento?->format('Y-m-d'),
                'valor' => (float) $o->valor,
                'vencida' => $o->tipo !== 'credito' && $o->dataReferencia() < now()->toDateString(),
            ]),
            'formas' => FormaPagamento::where('ativa', true)->orderBy('ordem')->orderBy('nome')->get(['id', 'nome', 'tipo', 'parcelas_max']),
            'servicos' => Servico::where('ativo', true)->where('papel', 'principal')->orderBy('nome')->get(['id', 'nome']),
            'veiculos' => $cliente->veiculos()->where('ativo', true)->get()->map(fn ($v) => ['id' => $v->id, 'rotulo' => $v->rotulo()]),
            'pontualidade' => \App\Servicos\Pontualidade::doCliente($cliente->id),
        ]);
    }

    public function confirmarRecebimento(Request $request, Cliente $cliente): RedirectResponse
    {
        $d = $request->validate([
            'ordens' => ['required', 'array', 'min:1'],
            'ordens.*' => ['integer'],
            'valor' => ['required', 'numeric', 'min:0', 'max:999999'],
            'forma_pagamento_id' => ['required', 'integer'],
            'parcelas' => ['nullable', 'integer', 'min:1', 'max:24'],
            'recebido_em' => ['required', 'date_format:Y-m-d'],
            'sobra' => ['nullable', 'in:credito,servico'],
            'falta' => ['nullable', 'in:debito,desconto'],
            'servico_id' => ['nullable', 'string', 'max:10'], // id do serviço ou "estadia"
            'veiculo_id' => ['nullable', 'integer'],
            'motivo' => ['nullable', 'string', 'max:200'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], ['ordens.required' => 'Marque as contas que foram pagas.']);
        [, $msg] = Baixa::receber($cliente, $d + ['origem' => 'gestao']);
        Boletos::tentarCancelarResolvidos(); // cobrança quitada aqui: o boleto dela não pode mais ser pago

        return redirect("/clientes/{$cliente->id}")->with('sucesso', 'Pagamento registrado: '.$msg.'.');
    }

    public function estornar(Request $request, Pagamento $pagamento): RedirectResponse
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:200']]);
        Baixa::estornar($pagamento, $d['motivo']);

        return back()->with('sucesso', 'Pagamento desfeito; as contas voltaram a ficar em aberto.');
    }

    /** estorno ao cliente: devolve o crédito dele (o Pix ou a transferência é feito no app do banco) */
    public function estornoCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        $d = $request->validate([
            'valor' => ['required', 'numeric', 'min:0.01'], 'estornado_em' => ['required', 'date_format:Y-m-d'],
            'forma_pagamento_id' => ['nullable', 'integer'], 'motivo' => ['required', 'string', 'max:200'],
        ], ['motivo.required' => 'Informe o motivo do estorno.', 'valor.required' => 'Informe o valor do estorno.']);
        $e = \App\Servicos\Estornos::estornar($cliente, $d);

        return back()->with('sucesso', 'Estorno de '.\App\Support\Formato::moeda((float) $e->valor).' registrado. O crédito do cliente foi baixado.');
    }

    public function desfazerEstorno(Request $request, \App\Models\Estorno $estorno): RedirectResponse
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:200']], ['motivo.required' => 'Informe o motivo.']);
        \App\Servicos\Estornos::desfazer($estorno, $d['motivo']);

        return back()->with('sucesso', 'Estorno desfeito; o crédito voltou para o cliente.');
    }
}
