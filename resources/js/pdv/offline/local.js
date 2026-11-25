import { ErroApi } from './servidor'
import { pacote } from './fila'
import { pessoa } from './ajuda'
import { OFFLINE } from './modulos'

/*
 * As rotas da API que o celular responde sozinho quando está sem internet (8.7), com a cópia do servidor (pacote) e a
 * fila. Mesmas respostas da API, para as telas funcionarem sem mudar. A base responde /eu, /inicio e o PIN; cada
 * módulo de ramo responde as rotas dele (resources/js/Modulos/<Nome>/pdv/offline.js; no Estacionamento, entrada e
 * saída de estadia). O resto pede internet.
 */

/** "Isso precisa de internet; entrada e saída de estadia funcionam sem." */
export function mensagemSemInternet() {
    const pode = OFFLINE.map((m) => m.DESCRICAO).filter(Boolean)
    return pode.length ? `Sem internet. Isso precisa de internet; ${pode.join(' e ')} funcionam sem.` : 'Sem internet. Isso precisa de internet.'
}

/** rotas da base que o celular sabe responder sem internet */
const ROTAS = [['GET', /^\/eu$/], ['GET', /^\/inicio$/], ['POST', /^\/pin\/conferir$/]]

const casa = (rotas, metodo, caminho) => rotas.some(([m, r]) => m === metodo && r.test(caminho.split('?')[0]))
export const sabeResponder = (metodo, caminho) => casa(ROTAS, metodo, caminho) || OFFLINE.some((m) => casa(m.ROTAS || [], metodo, caminho))

export async function responder(metodo, caminho, dados = {}) {
    const p = pacote()
    if (!p) throw new ErroApi('Sem internet e o celular ainda não tem a cópia do sistema. Abra o PDV uma vez com internet.', 0, { semInternet: true })
    const [rota, busca = ''] = caminho.split('?')
    const q = new URLSearchParams(busca)

    if (metodo === 'GET' && rota === '/eu') return { ...p.eu, pix_qr: false, sem_internet: true }
    if (metodo === 'GET' && rota === '/inicio') return Object.assign({}, ...OFFLINE.map((m) => m.inicio?.(p) || {}), { caixa: p.caixa })
    if (metodo === 'POST' && rota === '/pin/conferir') return { usuario: await pessoa(dados.pin) }
    for (const m of OFFLINE) {
        if (!casa(m.ROTAS || [], metodo, caminho)) continue
        const r = await m.responder(metodo, rota, q, dados, p)
        if (r !== undefined) return r
    }
    throw new ErroApi(mensagemSemInternet(), 0, { semInternet: true })
}
