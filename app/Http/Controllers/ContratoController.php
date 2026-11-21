<?php

namespace App\Http\Controllers;

use App\Models\MensalidadeContrato;
use App\Models\MensalidadePausa;
use App\Models\Veiculo;
use App\Servicos\Contratos;
use App\Support\EmpresaAtual;
use App\Support\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ações na mensalidade de um veículo, feitas pela ficha do cliente. */
class ContratoController extends Controller
{
    public function store(Request $request, Veiculo $veiculo): RedirectResponse
    {
        $d = $request->validate([
            'periodo' => ['required', 'string', 'max:30'],
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'inicio' => ['required', 'date'],
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', app(EmpresaAtual::class)->id())->where('ativa', true)],
        ]);
        $c = Contratos::criar($veiculo, $d);

        return redirect("/mensalidades/{$c->id}/primeira-cobranca?de=veiculo")->with('sucesso', 'Mensalidade cadastrada.');
    }

    public function reajustar(Request $request, MensalidadeContrato $contrato): RedirectResponse
    {
        $d = $request->validate([
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'a_partir' => ['required', 'date'],
            'periodo' => ['nullable', 'string', 'max:30'],
        ]);
        Contratos::reajustar($contrato, (float) $d['valor'], $d['a_partir'], $d['periodo'] ?? null);

        return back()->with('sucesso', 'Reajuste registrado.')->with('revisar_desconto', Contratos::revisarDesconto($contrato->cliente, 'Mensalidade reajustada.'));
    }

    public function encerrar(Request $request, MensalidadeContrato $contrato): RedirectResponse
    {
        $d = $request->validate(['data' => ['required', 'date'], 'motivo' => ['required', 'string', 'max:200']]);
        Contratos::encerrar($contrato, $d['data'], $d['motivo']);

        return back()->with('sucesso', 'Encerramento registrado.')->with('revisar_desconto', Contratos::revisarDesconto($contrato->cliente, 'Mensalidade encerrada.'));
    }

    public function desfazerEncerramento(MensalidadeContrato $contrato): RedirectResponse
    {
        Contratos::desfazerEncerramento($contrato);

        return back()->with('sucesso', 'Encerramento desfeito.');
    }

    public function pausar(Request $request, MensalidadeContrato $contrato): RedirectResponse
    {
        $d = $request->validate([
            'de' => ['required', 'date_format:Y-m'],
            'ate' => ['required', 'date_format:Y-m'],
            'taxa_tipo' => ['required', 'in:valor,pct'],
            'taxa_valor' => ['required', 'numeric', 'min:0', 'max:99999'],
            'motivo' => ['nullable', 'string', 'max:200'],
            // mensalidade do primeiro mês já gerada (parou no meio do mês)
            'mes_acao' => ['nullable', 'in:proporcional,cancelar,manter'],
            'mes_ultimo_dia' => ['nullable', 'date'],
            'mes_valor' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'mes_credito' => ['nullable', 'boolean'],
        ]);
        $mes = ['acao' => $d['mes_acao'] ?? 'manter', 'ultimo_dia' => $d['mes_ultimo_dia'] ?? null, 'valor' => $d['mes_valor'] ?? null];
        if (array_key_exists('mes_credito', $d) && $d['mes_credito'] !== null) {
            $mes['credito'] = (bool) $d['mes_credito'];
        }
        [, $avisos] = Contratos::pausar($contrato, $d['de'], $d['ate'], $d['taxa_tipo'], (float) $d['taxa_valor'], $d['motivo'] ?? null, $mes);

        return back()->with('sucesso', 'Pausa registrada.')->with('aviso', $avisos ? implode(' ', $avisos) : null)
            ->with('revisar_desconto', Contratos::revisarDesconto($contrato->cliente, 'Mensalidade pausada.'));
    }

    /** o cliente voltou no meio da pausa: proporcional do resto do mês, para receber agora ou na próxima cobrança */
    public function voltar(Request $request, MensalidadePausa $pausa): RedirectResponse
    {
        $d = $request->validate([
            'data' => ['required', 'date'],
            'quando' => ['required', 'in:agora,proximo'],
            'valor' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
        ]);
        $cliente = $pausa->contrato->cliente;
        [$ordem, $avisos] = Contratos::voltar($pausa, $d['data'], $d['quando'], isset($d['valor']) ? (float) $d['valor'] : null);
        $aviso = $avisos ? implode(' ', $avisos) : null;
        $desconto = Contratos::revisarDesconto($cliente, 'O cliente voltou da pausa.');
        if ($ordem && $d['quando'] === 'agora') {
            return redirect("/clientes/{$cliente->id}/receber")->with('sucesso', 'Pausa encerrada. Receba o proporcional ('.Formato::moeda((float) $ordem->valor).').')->with('aviso', $aviso);
        }

        return back()->with('sucesso', $ordem ? 'Pausa encerrada. O proporcional ('.Formato::moeda((float) $ordem->valor).') entra na próxima cobrança.' : 'Pausa encerrada.')
            ->with('aviso', $aviso)->with('revisar_desconto', $desconto);
    }

    public function removerPausa(MensalidadePausa $pausa): RedirectResponse
    {
        Contratos::removerPausa($pausa);

        return back()->with('sucesso', 'Pausa removida.');
    }
}
