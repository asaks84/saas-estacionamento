# Documentação do ERP Local

Documentação detalhada do desenvolvimento do novo sistema do Local Estacionamento.
Comece pela visão, depois o modelo de dados e as regras de negócio.

| # | Documento | Conteúdo |
|---|---|---|
| 00 | [Glossário](00-glossario.md) | O que cada termo significa no sistema |
| 01 | [Visão e princípios](01-visao-e-principios.md) | Objetivo, princípios, o que fica para depois |
| 02 | [Tecnologia e infraestrutura](02-tecnologia-e-infraestrutura.md) | Laravel, React, Bootstrap, hospedagem, ambiente local, padrões |
| 03 | [Modelo de dados](03-modelo-de-dados.md) | Tabelas, regras gerais, fluxo do dinheiro, veículos sem placa |
| 04 | [Migração dos dados](04-migracao-de-dados.md) | De onde vem cada dado do sistema atual |
| 05 | [Regras de negócio](05-regras-de-negocio.md) | Tarifador, mensalidade, baixa, convênio, cobrança |
| 06 | [Módulos](06-modulos.md) | Venda de serviços, baixa, cobrança, gastos, contas a pagar, cartão |
| 07 | [PDV](07-pdv.md) | API, modo de emergência, ticket |
| 08 | [Segurança, permissões e LGPD](08-seguranca-permissoes-lgpd.md) | Permissões, sessão, segredos, dados pessoais |
| 09 | [Preparação para SaaS](09-preparacao-saas.md) | O que fica pronto agora e o que fica para a venda |
| 10 | [Plano de etapas](10-plano-de-etapas.md) | As 8 etapas, portões e situação |
| 11 | [Comparativo com o mercado](11-comparativo-mercado.md) | Diferenças com ERPs e plataformas de cobrança |
| 12 | [Registro de decisões](12-decisoes.md) | Todas as decisões, com data e motivo |
| 13 | [Desenvolvimento](13-desenvolvimento.md) | Organização do código, regras de cada tela nova, testes e comandos |
| 14 | [Publicação e virada](14-publicacao.md) | Botão Publicar, servidor, primeira publicação, backup, virada |
| 15 | [Plano do PDV (etapa 8)](15-plano-pdv.md) | Ponto de partida, escopo da 1ª versão, ordem de construção, decisões em aberto |
| 17 | [Auditoria de segurança](17-auditoria-seguranca.md) | Auditorias feitas, achados e situação de cada um |

**Projeto SaaS** (só neste repositório, `saas-estacionamento`):

| # | Documento | Conteúdo |
|---|---|---|
| S01 | [Plano de comercialização](saas/01-plano-comercializacao.md) | Onde estamos, lacunas no código, fases, riscos e perguntas em aberto |
| S02 | [Decisões do SaaS](saas/02-decisoes.md) | Decisões da venda, com data e motivo |
| S03 | [Suporte com IA](saas/03-suporte-com-ia.md) | Até onde dá para dar suporte usando IA e o que precisa existir antes |
| S04 | [Vários produtos SaaS](saas/04-multiplos-produtos.md) | Núcleo comum e módulos por ramo: estrutura de pastas, como criar um módulo, o que ainda liga o núcleo ao estacionamento |

Origem: documento "Local — Arquitetura do produto (Laravel)" (https://claude.ai/artifact/3SxbV1v1KN3sKg6X4uX7te), exportado em 09/11/2026. **A partir daqui, a fonte oficial é esta pasta `docs/`.**
