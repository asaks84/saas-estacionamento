import { chamar, ErroApi, token, VERSAO } from './offline/servidor'
import { marcarSemInternet, novoUuid, semInternet } from './offline/estado'
import { agendar, atualizarPacote, pacote } from './offline/fila'
import { lembrarPin } from './offline/pins'
import { mensagemSemInternet, responder, sabeResponder } from './offline/local'
import { OFFLINE } from './offline/modulos'

export { ErroApi, token, VERSAO }

/*
 * Conversa com a API do PDV (/api/pdv). Com internet, vai ao servidor. Sem internet (8.7), as rotas que os módulos
 * de ramo sabem responder (no Estacionamento, entrada e saída de estadia) são respondidas pelo próprio celular
 * (offline/local.js) e as ações entram numa fila que sobe quando a internet volta. Cada ação leva um código único:
 * se a internet cair no meio e a ação for parar também na fila, o servidor não lança duas vezes. Cada módulo pode
 * completar os dados antes de mandar (ex.: a entrada de estadia leva o código do QR, gerado aqui).
 */
const ehAcao = (metodo, caminho) => metodo === 'POST' && OFFLINE.some((m) => (m.ACOES || []).some((r) => r.test(caminho)))

let renovar = null
const renovarPacote = () => { clearTimeout(renovar); renovar = setTimeout(() => atualizarPacote(), 1500) }

export async function api(metodo, caminho, dados) {
    const local = sabeResponder(metodo, caminho)
    const acao = ehAcao(metodo, caminho)
    if (acao) {
        dados = { ...dados, acao_uuid: dados?.acao_uuid || novoUuid() }
        for (const m of OFFLINE) dados = m.preparar ? m.preparar(metodo, caminho, dados) : dados
    }
    // feito sem internet e que ainda não subiu (ex.: estadia local): só o celular conhece
    const soLocal = OFFLINE.some((m) => m.SO_LOCAL?.test(caminho))
    if (local && pacote() && (semInternet() || soLocal)) return responder(metodo, caminho, dados)
    try {
        const j = await chamar(metodo, caminho, dados)
        if (caminho === '/pin/conferir') lembrarPin(dados.pin, j.usuario)
        if (acao) renovarPacote()
        return j
    } catch (e) {
        if (e.semInternet) {
            marcarSemInternet(true)
            agendar(20000)
            if (local && pacote()) return responder(metodo, caminho, dados)
            throw new ErroApi(mensagemSemInternet(), 0, { semInternet: true })
        }
        throw e
    }
}
