import { useEffect, useState } from 'react'
import { api } from '@/pdv/api'
import { rolarAoTopo, useAvisos } from '@/pdv/visual'
import Entrada from './telas/Entrada'
import Saida from './telas/Saida'
import Estadias from './telas/Estadias'
import EstadiaEntrada from './telas/EstadiaEntrada'
import EstadiaSaida from './telas/EstadiaSaida'
import Lavagens from './telas/Lavagens'

/*
 * O que o módulo Estacionamento encaixa no PDV (o app é um só; a base fica em resources/js/pdv):
 * - INICIO: atalhos e cartões do Início (os números vêm de /api/pdv/inicio, que cada módulo completa);
 * - TELAS: os nomes de tela que este módulo abre;
 * - Telas: o componente que mostra essas telas e cuida da navegação entre elas.
 * O modo sem internet do módulo fica em ./offline.js (sem tela, para a base não depender daqui).
 */
export const MODULO = 'estacionamento'

const numero = (n, rotulo) => (n > 0 ? `${n} ${rotulo}` : null)

export const INICIO = {
    atalhos: [
        { tela: 'entrada', icone: 'mais', espessura: 2.2, titulo: 'Entrada', legenda: 'estadia ou lavagem' },
        { tela: 'saida', icone: 'qr', titulo: 'Saída', legenda: 'ler qualquer ticket', contorno: true },
    ],
    cartoes: [
        { tela: 'estadias', icone: 'carro', tamanho: 30, titulo: 'Estadia', legenda: 'Pátio e histórico', numero: (r) => numero(r?.patio, 'no pátio') },
        { tela: 'lavagens', icone: 'gota', tamanho: 28, titulo: 'Lavagem', legenda: 'Etapas e entrega', numero: (r) => numero(r?.lavagens, 'em andamento') },
    ],
}

export const TELAS = ['entrada', 'saida', 'estadias', 'lavagens']

/** telas do estacionamento: começa em "inicial" (vinda do Início) e volta ao Início por onSair */
export function Telas({ inicial, eu, onSair }) {
    const avisos = useAvisos()
    const [tela, setTela] = useState(inicial)
    const [origem, setOrigem] = useState('inicio') // para onde voltar depois de uma entrada/saída de estadia
    const [estadiaId, setEstadiaId] = useState(null)
    const [lavagem, setLavagem] = useState(inicial === 'lavagens' ? { inicio: null, id: null } : null) // { inicio: 'nova' | 'entrega', id }
    const [opcoes, setOpcoes] = useState(null)   // tamanhos, formas e convênios das estadias

    const carregarOpcoes = () => api('GET', '/estadias/opcoes').then(setOpcoes).catch((e) => avisos.erro(e.message))
    useEffect(() => { if (inicial === 'estadias') carregarOpcoes() }, []) // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(() => { rolarAoTopo() }, [tela])

    const ir = (t) => (t === 'inicio' ? onSair() : setTela(t))
    // sempre busca de novo (formas, tamanhos e convênios mudam na gestão); enquanto chega, a tela usa a lista anterior
    const estadiaEntrada = (de) => { setOrigem(de); setTela('estadia-entrada'); carregarOpcoes() }
    const estadiaSaida = (id, de) => { setOrigem(de); setEstadiaId(id); setTela('estadia-saida'); carregarOpcoes() }
    const feitoEstadia = (msg) => { avisos.ok(msg); ir(origem) }
    const abrirLavagem = (inicio, id = null) => { setLavagem({ inicio, id }); setTela('lavagens') }
    const carregandoOpcoes = <div className="text-muted py-4 text-center">Carregando…</div>

    return (
        <>
            {tela === 'entrada' && <Entrada onVoltar={onSair} onEstadia={() => estadiaEntrada('inicio')} onLavagem={() => abrirLavagem('nova')} />}
            {tela === 'saida' && <Saida onVoltar={onSair} onEstadia={(id) => estadiaSaida(id, 'inicio')} onLavagem={(id) => abrirLavagem('entrega', id)} />}
            {tela === 'estadias' && <Estadias onVoltar={onSair} onEntrada={() => estadiaEntrada('estadias')} onSaida={(id) => estadiaSaida(id, 'estadias')} />}
            {tela === 'estadia-entrada' && (opcoes ? <EstadiaEntrada eu={eu} opcoes={opcoes} onVoltar={() => setTela(origem === 'inicio' ? 'entrada' : 'estadias')} onFeito={feitoEstadia} /> : carregandoOpcoes)}
            {tela === 'estadia-saida' && (opcoes ? <EstadiaSaida eu={eu} opcoes={opcoes} id={estadiaId} onVoltar={() => ir(origem)} onFeito={feitoEstadia} /> : carregandoOpcoes)}
            {tela === 'lavagens' && <Lavagens eu={eu} inicio={lavagem?.inicio} entregaInicial={lavagem?.id}
                onVoltar={() => (lavagem?.inicio === 'nova' ? setTela('entrada') : onSair())} onSair={onSair} />}
        </>
    )
}
