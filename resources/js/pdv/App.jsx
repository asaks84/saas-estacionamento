import { useCallback, useEffect, useState } from 'react'
import { api, token } from './api'
import Parear from './telas/Parear'
import Inicio from './telas/Inicio'
import Aparelho from './telas/Aparelho'
import CadastrarPin from './telas/CadastrarPin'
import TesteImpressao from './telas/TesteImpressao'
import Caixa from './telas/Caixa'
import { moduloDaTela } from './modulos'
import { PinProvider } from './Pin'
import { AvisosProvider, Cidade, Logo, rolarAoTopo, useAvisos, useTema } from './visual'
import { carregarLogo } from './impressao/modelo'
import { instalarVoltar } from './voltar'
import AvisoRede from './offline/AvisoRede'
import { limparTudo, ouvir } from './offline/estado'
import { iniciarFila, tirarRecusadas } from './offline/fila'

/**
 * PDV: um app só, com a base comum a todos os ramos (pareamento, PIN de cada pessoa, impressão, caixa, Pix com QR,
 * tema claro/escuro, voltar do celular e modo sem internet) e as telas de cada módulo de ramo ligado
 * (resources/js/Modulos/<Nome>/pdv; no Estacionamento: entrada, saída, estadias e lavagens). Roteiro: docs/15-plano-pdv.md.
 */
export default function App() {
    return <AvisosProvider><Pdv /></AvisosProvider>
}

function Pdv() {
    const avisos = useAvisos()
    const [tema, setTema] = useTema()
    const [eu, setEu] = useState(null)       // dados do aparelho e da empresa
    const [erro, setErro] = useState(null)
    const [tela, setTela] = useState('inicio')
    const [entrada, setEntrada] = useState(0)    // cada ida a uma tela de módulo começa do zero
    const [carregando, setCarregando] = useState(!!token.get())

    const carregar = useCallback(() => {
        if (!token.get()) { setEu(null); setCarregando(false); return }
        setCarregando(true)
        api('GET', '/eu').then((j) => { setEu(j); setErro(null) }).catch((e) => {
            if (e.parear) { token.set(null); setEu(null) } else setErro(e.message)
        }).finally(() => setCarregando(false))
    }, [])
    useEffect(() => { carregar() }, [carregar])
    // modo sem internet (8.7): a fila tenta subir sozinha; o que foi para o gerente conferir aparece uma vez
    const pareado = !!eu
    useEffect(() => { if (pareado) iniciarFila() }, [pareado])
    useEffect(() => ouvir(() => {
        const r = tirarRecusadas()
        if (r.length) avisos.erro(r.length === 1 ? r[0] : `${r.length} ações feitas sem internet foram para o gerente conferir na gestão.`, 'Feito sem internet')
    }), [avisos])
    // hora do celular muito diferente da do servidor: a hora das ações sem internet sairia errada
    const horaServidor = eu?.servidor_hora
    useEffect(() => {
        if (!horaServidor || eu?.sem_internet) return
        const dif = Math.round((Date.now() - new Date(horaServidor).getTime()) / 60000)
        if (Math.abs(dif) >= 5) avisos.erro(`A hora do celular está ${Math.abs(dif)} min ${dif > 0 ? 'adiantada' : 'atrasada'}. Acerte a data e hora automáticas do celular.`, 'Hora do celular')
    }, [horaServidor]) // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(() => { instalarVoltar() }, []) // voltar do celular volta uma etapa, nunca fecha o app
    // volta ao Início: relê os dados do aparelho em silêncio (ex.: o Pix com QR foi ligado na gestão depois de o app abrir)
    useEffect(() => {
        if (tela !== 'inicio' || !token.get()) return
        api('GET', '/eu').then(setEu).catch(() => { /* mantém o que tinha */ })
    }, [tela])
    useEffect(() => { rolarAoTopo() }, [tela])
    // logo da impressão (vem do ERP) já convertido em pontos para a impressora
    const logoUrl = eu?.impressao?.logo
    useEffect(() => { carregarLogo(logoUrl) }, [logoUrl])

    if (carregando) return <div className="pdv-centro text-muted"><Logo altura={48} />Carregando…</div>
    if (!eu) {
        return erro
            ? <div className="pdv-centro"><Logo altura={48} /><div className="alert alert-danger">{erro}</div><button className="btn btn-primary btn-lg" onClick={carregar}>Tentar de novo</button></div>
            : <Parear onPareado={carregar} />
    }

    const voltar = () => setTela('inicio')
    const irPara = (t) => { setEntrada((n) => n + 1); setTela(t) }
    const modulo = moduloDaTela(eu, tela)

    if (tela === 'inicio') {
        return (
            <PinProvider memoriaMin={eu.pin_memoria_min}>
                <Cidade />
                <div className="pdv-app"><AvisoRede /><Inicio eu={eu} irPara={irPara} /></div>
            </PinProvider>
        )
    }
    return (
        <PinProvider memoriaMin={eu.pin_memoria_min}>
            <Cidade />
            <div className="pdv-app">
                <AvisoRede />
                <main className="pdv-corpo">
                    {modulo && <modulo.Telas key={`${modulo.MODULO}-${entrada}`} inicial={tela} eu={eu} onSair={voltar} />}
                    {tela === 'caixa' && <Caixa eu={eu} onVoltar={voltar} />}
                    {tela === 'aparelho' && <Aparelho eu={eu} tema={tema} setTema={setTema} onVoltar={voltar} irPara={setTela} onDesparear={() => { token.set(null); limparTudo(); setEu(null) }} />}
                    {tela === 'cadastrar-pin' && <CadastrarPin onVoltar={() => setTela('aparelho')} />}
                    {tela === 'impressao' && <TesteImpressao eu={eu} onVoltar={voltar} />}
                </main>
            </div>
        </PinProvider>
    )
}
