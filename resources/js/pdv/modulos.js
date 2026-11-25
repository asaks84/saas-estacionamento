// Telas que cada módulo de ramo encaixa no PDV (resources/js/Modulos/<Nome>/pdv/index.jsx): atalhos e cartões do
// Início, nomes de tela e o componente Telas. Só aparecem os módulos ligados para a empresa do aparelho (eu.modulos).
const TODOS = Object.values(import.meta.glob('../Modulos/*/pdv/index.jsx', { eager: true }))

export const modulosLigados = (eu) => TODOS.filter((m) => !eu?.modulos || eu.modulos.includes(m.MODULO))

/** o módulo que abre esta tela (ou nada, se a tela é da base) */
export const moduloDaTela = (eu, tela) => modulosLigados(eu).find((m) => (m.TELAS || []).includes(tela)) || null
