// Itens de menu do módulo Estacionamento. Entram no menu do núcleo (resources/js/lib/menu.js):
// "grupo" diz em que grupo entra; "antes"/"depois" (href de um item do núcleo) dizem onde. Sem isso, vai para o fim do grupo.
// Só aparecem para empresas com o módulo ligado (props.modulos).
export const MODULO = 'estacionamento'

export const MENU = [
    { grupo: 'Operação', antes: '/mensalidades/gerar', rotulo: 'Pátio', href: '/patio', icone: 'relogio', acende: ['/patio', '/estadias', '/lavagens'] },
    { grupo: 'Gestão', rotulo: 'Convênios', href: '/convenios', icone: 'aperto', permissao: 'convenios', acende: ['/convenios'] },
]

// abas de Sistema › Configurações
export const CONFIGURACOES = [
    { depois: '/configuracoes/tipos', rotulo: 'Tabelas de estadia', href: '/configuracoes/tarifas', permissao: 'configuracoes', acende: ['/configuracoes/tarifas'] },
    { depois: '/configuracoes/servicos', rotulo: 'Etapas da lavagem', href: '/configuracoes/etapas', permissao: 'configuracoes', acende: ['/configuracoes/etapas'] },
]
