# Plano de comercialização (rascunho)

> Parte da documentação do projeto SaaS. Índice: [docs/README.md](../README.md)
>
> **Situação: rascunho para discussão (25/11/2026).** Nenhuma decisão daqui foi tomada; as decisões entram em [02-decisoes.md](02-decisoes.md) quando forem fechadas. Complementa [09-preparacao-saas.md](../09-preparacao-saas.md).

Objetivo: transformar o ERP do Local em um produto que outros estacionamentos assinam (SaaS), sem quebrar a operação do Local, que continua sendo o primeiro cliente.

## 1. Onde estamos

**O que já está pronto para várias empresas**

- `empresa_id` em toda tabela de negócio, filtro automático (`PertenceAEmpresa`) e testes de leitura entre empresas.
- Credenciais do Inter, do WhatsApp e da NFS-e por empresa, criptografadas.
- Rotinas automáticas (Inter, régua do WhatsApp) já passam por todas as empresas ativas, uma de cada vez.
- Numeração própria por empresa (cobrança, OS, ticket), níveis de acesso e permissões configuráveis, preços, tarifas e convênios por tela.

**Lacunas encontradas no código (25/11/2026)**

| # | Lacuna | Por que importa no SaaS |
|---|---|---|
| L1 | Login único no sistema todo (`usuarios.login` é único geral) | Dois clientes não podem ter um usuário "admin" ou "joao"; e o login denuncia que outra empresa já usa aquele nome |
| L2 | Telas públicas que pegam "a primeira empresa": `PrivacidadeController`, redirecionamento do `CadastroPublicoController`, logo/nome no `HandleInertiaRequests` quando só existe uma empresa | Com a segunda empresa, a política de privacidade e o cadastro público mostram o Local para todo mundo |
| L3 | Backup é do **banco inteiro** e a tela de backup fica dentro do sistema (permissão do cliente) | Um administrador de cliente baixaria os dados de todas as empresas. Precisa virar ferramenta só da plataforma, e o cliente ganha uma "exportação dos meus dados" |
| L4 | Arquivos enviados (comprovantes, holerites, faturas de cartão) numa pasta comum por mês, sem separar por empresa | Hoje o download confere o registro (seguro), mas sem pasta por empresa não dá para exportar, apagar ou medir o espaço de um cliente |
| L5 | Webhook do WhatsApp com um endereço só | Precisa achar a empresa pelo número que recebeu a mensagem (`phone_number_id`) e conferir a assinatura com a chave daquela empresa |
| L6 | Marca e textos do Local no código: logo e nome no PDV (`pdv/visual.jsx`, `Parear.jsx`), nome da pasta de backups no Google Drive, desenho dos prédios de Santos no Início, padrões da NFS-e de Santos (`ConfigNfse`), endereço `erp.localestacionamento.com.br` | Fere a regra "nada de texto comercial fixo" e aparece para o cliente de outra cidade |
| L7 | Não existe um "dono da plataforma": todo usuário pertence a uma empresa | Sem isso não há como cadastrar empresa, suspender, ver uso ou dar suporte com registro |
| L8 | Pendências do próprio produto: criptografia de CPF/celular/e-mail (decisão de 14/11), PDV 8.6 e 8.7 | Vender com essas pendências aumenta o risco (LGPD) e o suporte |

## 2. Fases propostas

A ordem importa: primeiro isolar e proteger, depois vender para poucos com acompanhamento de perto, e só depois automatizar a venda.

### Fase 0 — Decisões de negócio (sem código)

- **Modelo:** SaaS (um sistema, várias empresas — recomendado, já é a arquitetura) ou instalação por cliente (um servidor para cada; mais caro de manter, não recomendo).
- **Público-alvo:** estacionamento pequeno com mensalistas e lavagem (igual ao Local) primeiro. Rede grande, shopping e cancela automática ficam fora no começo.
- **Planos e módulos:** o que é básico e o que é adicional (ex.: PDV por aparelho, WhatsApp com custo repassado, NFS-e, contas a pagar, Inter).
- **Preço:** por unidade/pátio, por aparelho ou por faixa de clientes ativos.
- **Empresa que vende:** CNPJ próprio (separado do estacionamento é mais seguro), CNAE de software, regime tributário, contador.
- **Suporte:** quem atende, em que horário, por qual canal; quem corrige um erro grave no domingo.

### Fase 1 — Isolamento e segurança entre empresas

- L1: definir como a pessoa entra. Opções: (a) endereço por empresa (`cliente.dominio.com.br`) e login único dentro da empresa; (b) login por e-mail, único no sistema. Recomendo **(a)**, que já estava previsto na decisão de 09/11/2026 e não obriga funcionário de pátio a ter e-mail.
- L2: páginas públicas (privacidade, cadastro, ticket, cobrança) sempre resolvem a empresa pelo endereço ou pelo código público, nunca "a primeira".
- L3: backup completo só no painel da plataforma; exportação por empresa (planilhas + arquivos) para o cliente, que também serve para a saída do cliente (portabilidade, LGPD).
- L4: arquivos em `empresas/{id}/...`; migrar os existentes.
- L5: webhook do WhatsApp roteado pela empresa.
- L8: criptografia dos dados pessoais antes do primeiro cliente externo.
- Teste automático que percorre **todas** as rotas logado como empresa B tentando abrir ids da empresa A; auditoria de segurança repetida ([docs/17](../17-auditoria-seguranca.md)) com foco em vazamento entre empresas.

### Fase 2 — Painel da plataforma (mínimo)

- Usuário "plataforma", fora das empresas, com login separado e segundo fator obrigatório.
- Cadastrar empresa (nome, CNPJ, endereço, primeira unidade, primeiro administrador) e aplicar um **modelo inicial**: tipos de veículo, plano de contas, formas de pagamento, níveis de acesso, mensagens do WhatsApp.
- Ligar/desligar módulos por empresa (o menu e as rotas respeitam).
- Suspender e reativar empresa (só leitura quando suspensa, nunca apagar).
- **Entrar na conta do cliente para suporte**, com motivo, registro no log de atividades do cliente e aviso visível na tela.
- Visão de uso: empresas ativas, último acesso, erros recentes, rotinas que falharam.

### Fase 3 — Infraestrutura para vários clientes

A Hostinger compartilhada serve ao Local, mas não a vários pagantes:

- Rotinas rodam uma empresa após a outra no cron; com 20 empresas, a consulta do Inter e a régua podem passar do tempo. Precisa de fila de verdade (processo contínuo), o que a hospedagem atual não permite.
- Sem ambiente de testes (homologação): a publicação antiga ia direto para a produção (retirada em 25/11/2026; ver [docs/14](../14-publicacao.md)). Com clientes pagantes, toda publicação passa antes por um ambiente igual ao de produção.
- Proposta: VPS em São Paulo (dados no Brasil simplifica a LGPD) com painel de gestão (Forge, Ploi ou similar), banco de dados com backup automático e teste de restauração, monitoramento de erros e de site fora do ar, fila e agendador.
- Manter **um banco só** com `empresa_id` (já feito). Banco por cliente só se algum cliente grande exigir.

### Fase 4 — Jurídico e LGPD

- Termos de uso e SLA (disponibilidade, prazo de resposta, backup, o que acontece com os dados no cancelamento).
- Contrato de tratamento de dados: o estacionamento cliente é o **controlador** dos dados dos clientes dele; a empresa que vende o ERP é **operadora**. Listar os suboperadores (hospedagem, Meta, Inter, Google Drive).
- Política de privacidade da plataforma, encarregado (ou canal de atendimento) e plano de resposta a incidente (prazo de 3 dias úteis para comunicar a ANPD).
- Revisar o [docs/08](../08-seguranca-permissoes-lgpd.md) para o papel de operador.

### Fase 5 — Piloto com 1 a 3 estacionamentos

- Implantação feita por você, com acompanhamento de perto e preço de piloto.
- Objetivo principal: descobrir onde as **regras do Local** não servem para outros (tarifador, mês comercial de 30 dias, convênio, mensalidade, fluxo da lavagem) e transformar em configuração, não em código especial por cliente.
- Importação de clientes e veículos por planilha (o importador atual é só do sistema antigo do Local).
- Medir: tempo de implantação, chamados de suporte, o que cada um não usou.

### Fase 6 — Venda em escala

- Cobrança dos assinantes: no começo, pelo **próprio ERP** (a empresa que vende é uma empresa no sistema; os estacionamentos são clientes dela, com mensalidade, boleto do Inter, régua do WhatsApp e NFS-e). Reaproveita tudo que já foi testado. Gateway de cartão recorrente só se a demanda pedir.
- Suspensão automática por falta de pagamento, com aviso antes.
- Autoatendimento: assistente de implantação, assistentes do Inter, WhatsApp, NFS-e e impressora (lista de impressoras homologadas, decisão de 13/11/2026).
- NFS-e de outras cidades: o padrão nacional cobre os municípios que aderiram; os demais ficam fora até haver demanda.
- Site comercial, demonstração com dados fictícios, central de ajuda e histórico de novidades para o cliente.

## 3. Riscos (análise honesta)

- **Suporte é o maior gargalo, não o código.** Hoje o desenvolvimento depende de você e da IA, e você não programa em PHP. Com cliente pagante, um erro no fechamento de caixa num sábado precisa de alguém que resolva. Antes de vender em escala, vale ter um parceiro técnico ou um contrato de suporte de desenvolvimento.
- **Regras feitas sob medida para o Local.** O sistema foi desenhado preservando as regras do Local. Outros estacionamentos cobram diferente; sem piloto, o risco é vender e ter que criar exceções no código para cada cliente.
- **Uma publicação atinge todos.** Um erro que hoje afeta só o Local passa a afetar todos os clientes ao mesmo tempo. Ambiente de homologação e testes automáticos deixam de ser opcionais.
- **Vazamento entre empresas é o erro mais grave possível** num SaaS (responsabilidade como operador na LGPD e perda de confiança). Fase 1 antes de qualquer cliente externo.
- **Custo de infraestrutura e de terceiros** (WhatsApp cobra por conversa; hospedagem melhor; monitoramento) precisa entrar no preço.
- **Concorrência:** já existem sistemas de estacionamento no mercado; o diferencial do ERP (financeiro completo, cobrança com Pix/boleto e régua, NFS-e, PDV no celular) precisa estar claro no site e na demonstração.

## 4. Ordem sugerida

1. Fechar as pendências do produto (L8) e a Fase 0.
2. Fase 1 (isolamento) e o mínimo da Fase 2 (cadastrar empresa, modelo inicial, entrar como suporte).
3. Fase 3 (servidor novo com homologação) e Fase 4 (contratos).
4. Fase 5: piloto.
5. Fase 6 conforme o piloto mostrar.

## 5. Perguntas em aberto

- Vender como SaaS (recomendado) ou instalação por cliente?
- Vender por uma empresa nova (CNPJ separado) ou pelo próprio Local?
- Já existe algum estacionamento interessado para ser piloto?
- Quem faz o suporte e a manutenção técnica depois da venda?
- Entrada por endereço por empresa (`cliente.dominio`) ou por e-mail?
- Nome e domínio do produto (hoje o endereço e a marca são do Local)?
