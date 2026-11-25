# SaaS Estacionamento

> Planejamento do SaaS em [`docs/saas/`](docs/saas/01-plano-comercializacao.md). **Não publicar** daqui: a publicação configurada ainda não é a do SaaS (ver `CLAUDE.md`).

Novo sistema de gestão do **Local Estacionamento** (Santos/SP): mensalistas, estadias, lavagens e serviços, cobranças, recebimentos, convênios, contas a pagar, gestão de gastos e PDV no celular. Preparado para várias unidades e, no futuro, para ser vendido como SaaS.

> Repositório privado. Não tornar público.

## Situação

Etapas 1 (fundação), 2 (cadastros e configurações), 3 (dinheiro) e 4 (operação na gestão) prontas para conferência: login, níveis, usuários, empresa e unidades, clientes, veículos, mensalidades, fila de espera e cadastro público, tabelas de estadia, serviços, convênios, ordens de serviço, geração de mensalidades, cobranças, recebimentos, extrato bancário, contas em aberto, estadias, lavagens e serviços com quadro de etapas, e a importação desses dados do sistema atual. Etapa 5 concluída: virada em 24/11/2026, o ERP é o sistema oficial e o sistema atual fica só para consulta ([docs/14-publicacao.md](docs/14-publicacao.md)). Ver [docs/10-plano-de-etapas.md](docs/10-plano-de-etapas.md).

## Tecnologia

- **Laravel 13** (PHP 8.3+) + **MySQL**
- Telas: **Inertia 3 + React 19 + Bootstrap 5.3** (personalizado com o visual da marca, tema claro e escuro)
- PDV: PWA no celular, online primeiro, API com Laravel Sanctum
- Filas e tarefas agendadas pelo cron (hospedagem compartilhada)

## Documentação

Tudo em [`docs/`](docs/README.md): visão, modelo de dados, regras de negócio, módulos, PDV, segurança, plano de etapas e registro de decisões.

- [`HISTORICO.md`](HISTORICO.md) — alterações, da mais nova para a mais antiga.
- [`CLAUDE.md`](CLAUDE.md) — regras do projeto para o Claude.
- [`docs/13-desenvolvimento.md`](docs/13-desenvolvimento.md) — organização do código e regras de cada tela.

## Rodar no computador (WAMP)

Precisa de PHP 8.3 (o do WAMP), Composer, Node.js e Git.

1. **Bancos.** No phpMyAdmin, crie três bancos vazios com o agrupamento `utf8mb4_unicode_ci`: `saas` (o sistema), `erp_teste` e `erp_teste_legado` (testes automáticos).
2. **Instalar** (no terminal, dentro de `C:\wamp64\www\saas`):
   ```
   composer install
   npm install
   copy .env.example .env
   php artisan key:generate
   php artisan migrate
   php artisan erp:instalar
   npm run build
   ```
3. Abra o sistema e entre com o administrador criado no `erp:instalar`.

Conferir: `php artisan test` (testes automáticos).
No PowerShell, se o `npm` for bloqueado ("running scripts is disabled"), use `npm.cmd` no lugar.
Enquanto mexe nas telas, `npm run dev` atualiza o navegador sozinho (sem ele, rode `npm run build` depois de cada mudança).

Como o código está organizado e as regras de cada tela nova: [docs/13-desenvolvimento.md](docs/13-desenvolvimento.md).
