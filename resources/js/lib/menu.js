// Itens do menu. "permissao" esconde o item de quem não tem acesso
// (basta uma das permissões da lista). "rapido" coloca o item no menu de baixo do celular.
// Itens dos módulos de ramo (resources/js/Modulos/<Nome>/menu.js) entram no fim deste arquivo, marcados
// com "modulo": só aparecem quando o módulo está ligado (ver noMenu).
const MENU_NUCLEO = [
    {
        grupo: '',
        itens: [
            { rotulo: 'Início', href: '/', icone: 'casa', rapido: true, acende: ['/'] },
        ],
    },
    {
        grupo: 'Clientes',
        itens: [
            { rotulo: 'Clientes', href: '/clientes', icone: 'pessoas', rapido: true, acende: ['/clientes', '/veiculos'] },
            { rotulo: 'Fila de espera', href: '/pendentes', icone: 'fila', permissao: 'aprovar', contador: 'fila', acende: ['/pendentes'] },
            { rotulo: 'Recadastro', href: '/atualizacao-cadastral', icone: 'documento', permissao: 'aprovar', contador: 'atualizacoes', acende: ['/atualizacao-cadastral'] },
        ],
    },
    {
        grupo: 'Operação',
        itens: [
            { rotulo: 'Gerar mensalidades', href: '/mensalidades/gerar', icone: 'calendario', acende: ['/mensalidades'] },
            { rotulo: 'Ordens de serviço', href: '/ordens', icone: 'lista', acende: ['/ordens'] },
        ],
    },
    {
        grupo: 'Gestão',
        itens: [
            { rotulo: 'Contas em aberto', href: '/contas/abertas', icone: 'dinheiro', permissao: 'contas_aberto', rapido: true, acende: ['/contas'] },
            { rotulo: 'Cobranças', href: '/cobrancas', icone: 'documento', acende: ['/cobrancas'] },
            { rotulo: 'Avisos de cobrança', href: '/avisos', icone: 'alerta', acende: ['/avisos'] },
            { rotulo: 'Contas a pagar', href: '/pagar', icone: 'documento', permissao: 'contas_pagar', acende: ['/pagar', '/fornecedores'] },
            { rotulo: 'Resultado e caixa', href: '/resultado', icone: 'grafico', permissao: 'resultado', acende: ['/resultado'] },
            { rotulo: 'Funcionários', href: '/funcionarios', icone: 'pessoas', permissao: 'contas_pagar', acende: ['/funcionarios'] },
            { rotulo: 'Extrato bancário', href: '/extrato', icone: 'banco', permissao: 'extrato', acende: ['/extrato'] },
            { rotulo: 'Recebimentos', href: '/relatorios/recebimentos', icone: 'grafico', permissao: 'faturamento', acende: ['/relatorios/recebimentos'] },
            { rotulo: 'Notas fiscais', href: '/notas-fiscais', icone: 'documento', permissao: 'nfse', acende: ['/notas-fiscais'] },
        ],
    },
    {
        grupo: 'Sistema',
        itens: [
            { rotulo: 'Configurações', href: '/empresa', icone: 'engrenagem', permissao: 'configuracoes', acende: ['/empresa', '/unidades', '/configuracoes'] },
            { rotulo: 'Níveis de acesso', href: '/niveis', icone: 'escudo', permissao: 'configuracoes', acende: ['/niveis'] },
            { rotulo: 'Usuários', href: '/usuarios', icone: 'cadeado', permissao: 'usuarios', acende: ['/usuarios'] },
            { rotulo: 'Registro de atividades', href: '/atividades', icone: 'lista', permissao: 'usuarios', acende: ['/atividades'] },
            { rotulo: 'Incidentes (LGPD)', href: '/incidentes', icone: 'alerta', permissao: 'usuarios', acende: ['/incidentes'] },
        ],
    },
]

// Abas de Sistema › Configurações da empresa (componente AbasConfiguracoes)
const CONFIGURACOES_NUCLEO = [
    { rotulo: 'Empresa e unidades', href: '/empresa', permissao: 'configuracoes', acende: ['/empresa', '/unidades'] },
    { rotulo: 'Tipos de veículo', href: '/configuracoes/tipos', permissao: 'configuracoes', acende: ['/configuracoes/tipos'] },
    { rotulo: 'Lavagens e serviços', href: '/configuracoes/servicos', permissao: 'configuracoes', acende: ['/configuracoes/servicos'] },
    { rotulo: 'Plano de contas', href: '/configuracoes/plano-contas', permissao: 'configuracoes', acende: ['/configuracoes/plano-contas'] },
    { rotulo: 'Cobranças', href: '/configuracoes/pagamentos', permissao: 'configuracoes', acende: ['/configuracoes/pagamentos'] },
    { rotulo: 'API do banco', href: '/configuracoes/inter', permissao: 'configuracoes', acende: ['/configuracoes/inter'] },
    { rotulo: 'Nota fiscal (NFS-e)', href: '/configuracoes/nfse', permissao: 'nfse', acende: ['/configuracoes/nfse'] },
    { rotulo: 'WhatsApp', href: '/configuracoes/whatsapp', permissao: 'configuracoes', acende: ['/configuracoes/whatsapp'] },
    { rotulo: 'Backup', href: '/configuracoes/backup', permissao: 'configuracoes', acende: ['/configuracoes/backup'] },
    { rotulo: 'Aparelhos (PDV)', href: '/configuracoes/aparelhos', permissao: 'configuracoes', acende: ['/configuracoes/aparelhos'] },
    { rotulo: 'Impressão (PDV)', href: '/configuracoes/impressao', permissao: 'configuracoes', acende: ['/configuracoes/impressao'] },
]

// ---- módulos de ramo ----
const MODULOS = Object.values(import.meta.glob('../Modulos/*/menu.js', { eager: true }))

/** põe o item na lista antes/depois do href indicado (ou no fim) */
function encaixar(lista, item) {
    const ref = item.antes ?? item.depois
    const i = ref ? lista.findIndex((x) => x.href === ref) : -1
    if (i < 0) lista.push(item)
    else lista.splice(item.antes ? i : i + 1, 0, item)
}

export const MENU = MENU_NUCLEO.map((g) => ({ ...g, itens: [...g.itens] }))
export const CONFIGURACOES = [...CONFIGURACOES_NUCLEO]
for (const m of MODULOS) {
    for (const { grupo, ...item } of m.MENU ?? []) {
        let g = MENU.find((x) => x.grupo === grupo)
        if (!g) {
            g = { grupo, itens: [] }
            MENU.splice(MENU.length - 1, 0, g) // grupo novo entra antes de Sistema
        }
        encaixar(g.itens, { ...item, modulo: m.MODULO })
    }
    for (const item of m.CONFIGURACOES ?? []) encaixar(CONFIGURACOES, { ...item, modulo: m.MODULO })
}

/** o item aparece? (módulo ligado para a empresa; itens do núcleo sempre) */
export function noMenu(item, modulos) {
    return !item.modulo || (modulos ?? []).includes(item.modulo)
}

/** item aceso: o endereço que mais combina com a página atual */
export function itemAceso(url, itens) {
    const caminho = url.split('?')[0].replace(/\/+$/, '') || '/'
    let melhor = null
    let tamanho = -1
    for (const it of itens) {
        for (const pre of it.acende) {
            const casa = pre === '/' ? caminho === '/' : caminho === pre || caminho.startsWith(pre + '/')
            if (casa && pre.length > tamanho) {
                melhor = it.href
                tamanho = pre.length
            }
        }
    }
    return melhor
}
