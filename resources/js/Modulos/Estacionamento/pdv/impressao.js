import { linkTicket, moeda, n6 } from '@/pdv/impressao/modelo'

// Dados de cada papel do estacionamento (ticket e recibos de estadia e lavagem), a partir do que a API devolve.
// Os papéis e o modelo editável ficam no núcleo (resources/js/pdv/impressao/modelo.js).

export const dadosTicket = (r, eu) => ({
    empresa: eu.empresa, numero: n6(r.numero), servico: r.servico, placa: r.placa, modelo: r.modelo, tamanho: r.tipo, entrada: r.entrada,
    link: r.codigo ? linkTicket(r.codigo) : null,
    pago: r.pago ? { valor: r.pago.valor, forma: r.pago.forma } : (r.pago_entrada > 0 ? { valor: r.pago_entrada } : null),
})

// o ticket serve para estadia e lavagem; o serviço vem no próprio ticket
export const dadosTicketLavagem = (r, eu) => dadosTicket({ ...r, servico: r.servico || 'Lavagem' }, eu)

/** recibo de pagamento: um só papel para todo pagamento; cada origem preenche o que tem */
export const dadosReciboEstadia = (r, eu) => ({
    empresa: eu.empresa, titulo: `RECIBO · Nº ${n6(r.numero)}`, placa: r.placa, modelo: r.modelo, tamanho: r.tipo, entrada: r.entrada, saida: r.saida, tempo: r.tempo,
    itens: [
        ...(r.valor !== null && r.valor !== undefined ? [{ descricao: r.servico, valor: r.valor }] : []),
        ...(r.convenio ? [{ descricao: `Convênio ${r.convenio.nome || ''}`.trim(), texto: `-${moeda(r.convenio.valor)}` }] : []),
        ...(r.pago_entrada > 0 && r.saida ? [{ descricao: 'Pago na entrada', texto: `-${moeda(r.pago_entrada)}` }] : []),
    ],
    pago: r.pago, situacao: r.destino,
})

export const dadosReciboLavagem = (r, eu) => ({
    empresa: eu.empresa, titulo: `RECIBO · Nº ${n6(r.numero)}`, placa: r.placa, modelo: r.modelo, tamanho: r.tipo, entrada: r.entrada, saida: r.saida, rotuloSaida: 'Entrega',
    itens: r.itens, pago: r.pago, na_conta: (r.itens || []).filter((i) => i.conta).map((i) => i.conta),
})

