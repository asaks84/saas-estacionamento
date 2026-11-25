# Publicação

> Parte da documentação do sistema. Índice: [docs/README.md](README.md)

**Situação (25/11/2026): sem publicação.** Nada deste repositório fica no ar por enquanto. O workflow de publicação (`.github/workflows/publicar.yml`) e os scripts do servidor (`deploy/`) foram retirados; a publicação será configurada de novo quando existir o servidor do SaaS (decisão em [saas/02-decisoes.md](saas/02-decisoes.md)).

O roteiro antigo (botão Publicar, estrutura de pastas no servidor, primeira publicação, voltar versão) continua no histórico do Git, no commit `dd74fe6`, e serve de ponto de partida.

## O que o servidor futuro precisa ter

- PHP 8.3 e MySQL 8, com banco e `.env` só do servidor (nunca no Git).
- **Publicação automática pelo repositório** (testes, build, envio), nunca envio de arquivos à mão; e uma forma rápida de voltar à versão anterior.
- **Ambiente de testes (homologação)** antes da produção, para uma publicação não atingir todos os clientes de uma vez ([plano, Fase 3](saas/01-plano-comercializacao.md#fase-3--infraestrutura-para-vários-clientes)).
- **Cron chamando `php artisan schedule:run` a cada minuto** (agendamentos abaixo). O código ainda funciona sem processo contínuo; com fila de verdade no servidor novo, as rotinas por empresa podem sair do cron.
- Backup do banco com restauração testada e monitoramento de erros.

## Agendamentos (routes/console.php)

| Quando | O quê |
|---|---|
| todo dia às 6h05 | `pagar:gerar-fixas`: contas fixas a pagar até o mês seguinte |
| a cada 10 minutos | `inter:consultar`: boletos do Banco Inter em aberto (baixa dos pagos); sem o Inter ligado, não faz nada |
| a cada 10 minutos, das 7h às 20h | `whatsapp:regua`: avisos de cobrança do dia |
| todo dia às 3h10 | `backup:banco`: backup compactado e criptografado do banco em `storage/app/private/backups` |
| todo dia às 3h40 | `lgpd:limpar`: prazos de guarda da LGPD |

As tarefas rodam dentro do próprio `schedule:run` (`Schedule::call`), e o backup é feito em PHP, sem `mysqldump`, porque hospedagens compartilhadas costumam desligar o `proc_open`. Num servidor próprio isso pode mudar.
