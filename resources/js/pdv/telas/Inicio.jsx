import { useCallback, useEffect, useState } from 'react'
import { api } from '../api'
import { Icone, TopoInicio } from '../visual'
import { modulosLigados } from '../modulos'

// cartão da base: o caixa é de todos os ramos
const CAIXA = { tela: 'caixa', icone: 'carteira', titulo: 'Caixa', legenda: 'Receber conta, retirada, fechar' }

/** Início (8.8): atalhos e cartões de cada módulo de ramo ligado (no Estacionamento: Entrada, Saída, Estadia, Lavagem), Caixa, impressão e aparelho */
export default function Inicio({ eu, irPara }) {
    const [resumo, setResumo] = useState(null)
    const carregar = useCallback(() => api('GET', '/inicio').then(setResumo).catch(() => { /* sem números: os botões funcionam igual */ }), [])
    useEffect(() => {
        carregar()
        const t = setInterval(carregar, 60000)
        return () => clearInterval(t)
    }, [carregar])

    const modulos = modulosLigados(eu)
    const atalhos = modulos.flatMap((m) => m.INICIO?.atalhos || [])
    const cartoes = modulos.flatMap((m) => m.INICIO?.cartoes || [])
    return (
        <div className="pdv-inicio">
            <TopoInicio eu={eu} caixa={resumo ? resumo.caixa : undefined} />
            <div className="pdv-inicio-corpo">
                {atalhos.length > 0 && (
                    <div className="pdv-atalhos">
                        {atalhos.map((a) => (
                            <button key={a.tela} type="button" className={`pdv-atalho${a.contorno ? ' pdv-atalho-contorno' : ''}`} onClick={() => irPara(a.tela)}>
                                <Icone nome={a.icone} tamanho={30} espessura={a.espessura} />
                                <span><span className="pdv-atalho-titulo">{a.titulo}</span><span className="pdv-atalho-legenda">{a.legenda}</span></span>
                            </button>
                        ))}
                    </div>
                )}

                <div className="pdv-cartoes">
                    {[...cartoes, CAIXA].map((c) => {
                        const n = c.numero?.(resumo)
                        return (
                            <button key={c.tela} type="button" className="pdv-cartao" onClick={() => irPara(c.tela)}>
                                <span className="pdv-cartao-icone"><Icone nome={c.icone} tamanho={c.tamanho ?? 28} /></span>
                                <span className="pdv-cartao-texto"><strong>{c.titulo}</strong><span>{c.legenda}</span></span>
                                {n ? <span className="pdv-numero">{n}</span> : <Icone nome="seta" tamanho={20} espessura={2.4} className="pdv-seta" />}
                            </button>
                        )
                    })}
                </div>

                <div className="pdv-lista">
                    <button type="button" onClick={() => irPara('impressao')}><Icone nome="impressora" tamanho={22} />Impressão</button>
                    <button type="button" onClick={() => irPara('aparelho')}><Icone nome="celular" tamanho={22} />Este aparelho<span className="pdv-lista-extra">tema, PIN</span></button>
                </div>
            </div>
        </div>
    )
}
