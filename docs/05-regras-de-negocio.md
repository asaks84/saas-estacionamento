# Regras de negócio

> Parte da documentação do ERP Local. Índice: [docs/README.md](README.md)

Seis serviços concentram todas as contas de dinheiro; telas, PDV, API e relatórios só chamam esses serviços e nunca recalculam por conta própria. Cada um tem testes automáticos com casos reais do Local antes da virada.

| Serviço | O que decide | Hoje está em |
|---|---|---|
| Tarifador | Valor da estadia: tolerância, faixas, fração, diárias, pernoite | biblioteca Tarifador + configurações |
| Convênio | Quem paga quanto: convênio paga tudo, até um tempo ou desconto para quem usa | só cadastrado; aplicação é nova |
| Mensalidade | Geração do mês, primeira cobrança (proporcional de 30 dias, mês cheio, cortesia), reajuste | Collection + servicos_model |
| Preço de serviço | Principal + adicionais pelo tipo do veículo e pela unidade | configurações de serviços |
| Baixa | Recebe um pagamento e distribui entre ordens de serviço: total, parcial escolhida, sobra vira crédito, falta vira débito ou desconto | três caminhos diferentes (receber contas, lote, extrato) |
| Cobrança | Agrupa as ordens de serviço em aberto de um cliente ou convênio, com vencimento e número; gera o boleto | novo |

**Exemplo de teste que trava a publicação se falhar:** "SUV entrou 8h10, saiu 10h45, tabela Carros, sem convênio → R$ X". Os valores reais saem das tabelas que você já configurou.

## Detalhe das regras que já existem no sistema atual (preservar)

**Tarifador (estadia)**
- Tolerância: minutos iniciais grátis.
- Fração: minutos de cortesia antes de cobrar o próximo bloco.
- Faixas em sequência: `fixo` (valor único pela faixa) ou `bloco` (a cada N minutos, um valor); a última vai até o fim.
- Pacotes (diárias/períodos) funcionam como teto; com hora de início e fim viram pernoite/período (preço fechado se a estadia estiver toda na janela).
- Restante acima do maior pacote: volta às faixas ou só pacotes inteiros.
- Sempre escolhe a combinação mais barata para o cliente.
- Referência: `application/libraries/Tarifador.php` do sistema atual.

**Mensalidade**
- Mês comercial de 30 dias. Proporcional = mensalidade × (30 − min(dia, 30) + 1) / 30.
- Primeira cobrança (depois de aprovar cadastro ou adicionar veículo): só proporcional; proporcional + próximo mês; mês cheio; cortesia neste mês (gera só o próximo); não gerar. Vencimento do próximo mês no dia do cliente (dia 31 vira o último dia do mês).
- Geração mensal pula o veículo que já tem mensalidade de mês posterior gerada até o fim do mês de referência.
- Nunca duas mensalidades do mesmo mês para o mesmo veículo.

**Baixa e extrato**
- Total em aberto do cliente = itens em aberto − créditos.
- PIX menor que o total: escolher os itens pagos (pré-marca a combinação exata, mais antigos primeiro); falta nas marcadas vira desconto ou débito; sobra vira crédito.
- Desconto só com permissão.

**Convênio** (revisto em 17/11/2026)
- O convênio é um cliente (quem paga) com uma regra **por serviço**: a tela lista Estadia e cada serviço oferecido (lavagens e adicionais), menos mensalidade; cada serviço marcado ganha uma das opções:
  1. **Pagamento integral:** o convênio paga tudo.
  2. **Pagamento parcial:** o convênio paga até um limite em R$; o que passar fica com quem usou. Ex.: limite R$ 10: serviço de R$ 5 → convênio paga R$ 5; serviço de R$ 15 → convênio paga R$ 10 e quem usou paga R$ 5.
  3. **Limite de tempo** (só estadia): o convênio paga a estadia até X tempo; o excedente fica com quem usou.
- Serviço não marcado: quem usou paga o preço normal.
- A parte de quem usou é paga na hora (ou na entrega da lavagem); não vai para a conta de ninguém, porque são avulsos.
- O modo antigo "desconto para quem usa" sai: convênios que estiverem nele aparecem marcados para reconfigurar.
- Durante o paralelo, o convênio vem do sistema atual (modo antigo) e as regras por serviço são montadas a partir dele a cada importação: "paga tudo" vira pagamento integral na estadia e/ou em todos os serviços; "até um tempo" vira limite de tempo na estadia. Depois de salvo no ERP, a importação não mexe mais nas regras daquele convênio.
- Fechamento a cada X dias ou semanal gera uma cobrança só com as contas do convênio (pode haver mais de uma no mês), vencendo em 7 dias; junto com a mensalidade, a cobrança do mês com tudo o que o conveniado deve. O próximo fechamento conta a partir do último (ou da conta mais antiga, no primeiro).
- **Fechamento do convênio** (quando as ordens dele viram cobrança): junto com a mensalidade, a cada X dias, ou toda semana num dia escolhido.
- No dia do fechamento, o sistema **avisa** (tela inicial do ERP e tela do PDV) e quem confere gera a cobrança pelo botão **Gerar cobrança** do próprio aviso (no PDV, com PIN de gerente ou administrador). Nada é cobrado sozinho; passar para automático fica para depois, se fizer falta.

**Conta de outro cliente** (17/11/2026)
- Na venda e na saída da estadia, "na conta" tem o campo **Conta de:** para escolher qualquer cliente (vem preenchido com o dono do veículo, se houver). A ordem fica com a placa que entrou e o cliente que paga. Ex.: mensalista traz o carro do pai, que não está no sistema, e a lavagem vai para a mensalidade dele.

## Regras decididas para o ERP

- **Pausa e cancelamento agendado da mensalidade:** pausa = período em que a mensalidade cheia não é gerada; a pausa **pode ser cobrada**: ao pausar, define-se o valor da taxa de reserva (fixo ou % da mensalidade, com padrão configurável por empresa; zero = pausa grátis), que vira uma ordem de mensalidade de pausa em cada mês pausado; cancelamento agendado = data de encerramento registrada antes, o sistema gera a última mensalidade e para sozinho.
- **Pausa no meio do mês (21/11/2026):** se a mensalidade do primeiro mês da pausa já foi gerada, o sistema pergunta: cobrar o proporcional dos dias que o cliente veio (dia 1 até o último dia, base de 30 dias, valor ajustável), cancelar a mensalidade do mês ou manter cheia. Já paga: a diferença vira crédito só se o usuário escolher. Em cobrança já emitida, avisa para cancelar e emitir de novo (com o boleto). Mensalidade vinda do sistema atual não muda durante o paralelo.
- **Volta no meio da pausa (21/11/2026):** a pausa termina no mês anterior à volta (ou some, se começou no mesmo mês) e o resto do mês vira "Mensalidade proporcional dd/mm a dd/mm (volta da pausa)", para pagar agora (vence hoje e abre a tela de receber) ou na conta do mês seguinte (vence no dia do cliente no mês seguinte). Reserva de vaga em aberto do mês da volta é cancelada; paga, fica, com aviso.
- **Desconto de pontualidade (21/11/2026):** vale em qualquer forma de pagamento, só nas mensalidades cheias do mês pagas até o vencimento do cliente (proporcional, reserva de pausa, primeira mensalidade e cobrança com desconto negociado não têm). Regra da empresa em Configurações › Cobranças (% ou R$ por mensalidade) ou valor do cliente na ficha: o total em R$ por mês, digitado pelo usuário, que vale no lugar da regra (uma vez por mês de referência). O sistema não recalcula: reajuste, encerramento, pausa e remoção de veículo abrem o aviso para revisar. Na cobrança: o total cheio, a linha "Desconto de pontualidade das mensalidades (até dd/mm)" e o valor pagando até o vencimento.
- **Inadimplência:** o cliente fica inadimplente a partir do primeiro vencimento não pago. A cobrança é feita pelos funcionários (lista de inadimplentes, com contato e o que deve) ou pelo WhatsApp (régua de cobrança). Sem alerta separado de desistência.
- **Vigência de preços:** serviços, tabelas de estacionamento e contratos de mensalidade têm início e fim. Reajuste é uma nova versão com data de início, cadastrada com antecedência; na data, o sistema passa a usar a nova sozinho. Cada ordem usa o preço vigente na data em que aconteceu.

Ver também a seção de cobrança em [06-modulos.md](06-modulos.md).

- Saída do PDV durante queda de internet: cobra o valor estimado e o gerente confere depois.
- Caixa: só quem recebe dinheiro abre caixa; "operador de caixa" é uma qualificação no cadastro do usuário.
- Termos: **ordem de serviço** é todo item que o cliente deve (mensalidade, estadia, lavagem); **cobrança** agrupa as ordens em aberto e gera o boleto. Ver [00-glossario.md](00-glossario.md).
