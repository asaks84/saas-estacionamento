# Suporte com IA

> Parte da documentação do projeto SaaS. Índice: [docs/README.md](../README.md)

Dúvida de 25/11/2026: no início, dá para dar suporte usando IA para as correções de código?

**Resposta curta:** sim, no piloto (1 a 3 estacionamentos), desde que existam antes as proteções abaixo. Para vender em escala, não basta.

## Funciona bem para

- Corrigir erro com mensagem clara, ajustar tela, mudar regra pequena.
- Entender o que um trecho de código faz.
- Problemas que podem esperar algumas horas.

## Onde a IA sozinha não resolve

1. **Erro em produção com pressa** (caixa fechado errado no sábado, boleto que não baixou). A IA sugere a correção, mas alguém precisa saber se está certa antes de publicar; uma correção errada atinge todos os clientes de uma vez.
2. **Dinheiro e dados corrompidos.** Corrigir o código é a parte fácil; consertar os registros que ficaram errados, sem apagar dinheiro e respeitando estorno com motivo, exige conferência humana.
3. **IA errando com confiança.** Acontece, e quem não conhece PHP tem menos condição de perceber.

## Cuidados

- **Nunca colar dados de cliente na IA** (nome, CPF, placa, telefone, trechos do banco ou de log com esses dados). Como operador dos dados dos estacionamentos, isso seria repassar dados a um terceiro sem previsão em contrato (LGPD). Usar dados fictícios ou mascarados.
- **Contexto do projeto:** as regras estão no `CLAUDE.md` e em `docs/` (dinheiro só pelos serviços únicos, nunca apagar ordem, `empresa_id` sempre). A ferramenta precisa trabalhar dentro do repositório e ler a documentação; colar trechos soltos num chat é o caminho mais arriscado (ex.: esquecer o filtro de empresa).
- **Suporte não é só código:** a maior parte dos chamados é "como faço", configuração, impressora, Inter, WhatsApp — consome o tempo do dono, não da IA.

## O que precisa existir antes do piloto

1. Ambiente de testes (homologação) antes da produção.
2. Monitoramento de erros.
3. Backup com restauração testada.
4. Forma rápida de voltar à versão anterior.
5. Contato de um desenvolvedor PHP/Laravel para chamar por hora quando a IA não resolver, que já conheça o projeto antes de um problema grave.

Para a venda em escala: parceiro técnico ou contrato de suporte de desenvolvimento (ver riscos no [plano](01-plano-comercializacao.md#3-riscos-análise-honesta)).
