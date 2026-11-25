import { ErroApi } from './servidor'
import { conferirPinLocal } from './pins'

/* Ajudas do modo sem internet usadas pela base e pelos módulos de ramo (respostas no mesmo formato da API). */

export const erro = (msg, campo = null, status = 422) => new ErroApi(msg, status, campo ? { campos: { [campo]: [msg] } } : {})
export const placaDe = (p) => (String(p || '').toUpperCase().replace(/[^A-Z0-9]/g, '') || null)
export const br = (t) => (t ? `${t.slice(8, 10)}/${t.slice(5, 7)}/${t.slice(0, 4)} ${t.slice(11, 16)}` : null)
export const moeda = (v) => `R$ ${Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`

/** quem está atendendo, pelo PIN guardado no celular */
export async function pessoa(pin) {
    try {
        return await conferirPinLocal(pin)
    } catch (e) {
        throw erro(e.message, 'pin')
    }
}
