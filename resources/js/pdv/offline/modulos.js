// Modo sem internet de cada módulo de ramo (resources/js/Modulos/<Nome>/pdv/offline.js). Só a parte sem tela,
// para a base do PDV (api.js, offline/local.js) não depender das telas dos módulos.
export const OFFLINE = Object.values(import.meta.glob('../../Modulos/*/pdv/offline.js', { eager: true }))
