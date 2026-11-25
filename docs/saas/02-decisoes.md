# Registro de decisões

> Parte da documentação do projeto SaaS. Índice: [docs/README.md](../README.md)

Cada decisão da venda entra aqui, a mais nova em cima, com data e motivo.

| Data | Decisão | Motivo |
|---|---|---|
| 25/11/2026 | **Tudo num repositório só**, organizado em núcleo + módulos por ramo (`app/Modulos/<Nome>`, `resources/js/Modulos/<Nome>`, `tests/Modulos/<Nome>`). O estacionamento virou o primeiro módulo. Estrutura e como criar um módulo em [04-multiplos-produtos.md](04-multiplos-produtos.md) | Preparar para N produtos com o mesmo núcleo, sem copiar as regras de dinheiro |
| 25/11/2026 | **Cortada a ligação com o ERP Local** (`upstream`): este repositório não recebe mais as correções de lá; correção urgente que valha para os dois é feita nos dois. A migração do Local para dentro do SaaS fica **sem prazo** | Mover as pastas para os módulos faria quase todo merge do Local dar conflito |
| 25/11/2026 | Novos SaaS (agendamento de serviços, locação de espaços e outros) saem deste sistema: **um sistema só, com núcleo comum e módulos por ramo** ligados por empresa, cada produto com marca e domínio próprios. Detalhes em [04-multiplos-produtos.md](04-multiplos-produtos.md) | As regras de dinheiro são o que há de mais testado; uma cópia por produto obrigaria repetir cada correção em vários lugares |

## Em aberto

Vindas do [plano de comercialização](01-plano-comercializacao.md#5-perguntas-em-aberto):

- Vender como SaaS (um sistema para todos) ou instalação por cliente?
- Vender por uma empresa nova (CNPJ separado) ou pelo próprio Local?
- Algum estacionamento interessado em ser piloto?
- Quem faz o suporte e a manutenção técnica depois da venda?
- Entrada por endereço por empresa (`cliente.dominio.com.br`) ou por e-mail?
- Nome e domínio do produto?
