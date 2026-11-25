# Vários produtos SaaS a partir deste sistema

> Parte da documentação do projeto SaaS. Índice: [docs/README.md](../README.md)
>
> Decisões de 25/11/2026 (ver [02-decisoes.md](02-decisoes.md)). Guia para quando surgir um SaaS novo além do de estacionamento: agendamento de serviços (salão de beleza, barbearia), locação de espaços e outros.

## 1. Organização decidida

**Um repositório e um sistema só, com um núcleo comum e módulos por ramo.**

- **Núcleo:** o que serve a qualquer negócio de serviço. Todos os produtos usam.
- **Módulos por ramo:** Estacionamento (o atual), Agenda, Locação e outros que surgirem. Cada empresa assinante tem um ramo e os módulos que contratou. O menu e as rotas respeitam isso (ligar/desligar por empresa entra com o painel, Fase 2 do [plano](01-plano-comercializacao.md#fase-2--painel-da-plataforma-mínimo)).
- **Cada produto com marca e domínio próprios** (ex.: um endereço para o estacionamento e outro para a agenda), todos apontando para o mesmo sistema. A marca, o logo e os termos da tela vêm da configuração do produto ou da empresa, nunca do código.
- **Nada de repositório por produto.** As regras de dinheiro (ordem, cobrança, baixa, estorno, Inter, NFS-e) são a parte mais testada e mais sensível; com uma cópia por produto, cada correção teria que ser repetida em vários lugares.
- **Sem ligação com o ERP Local.** Este repositório não recebe mais as correções do `erp-local` pelo `upstream` (a reorganização das pastas tornou o merge inviável). Correção urgente que valha para os dois é feita nos dois. A migração do Local para dentro do SaaS (o Local como uma empresa daqui) fica sem prazo.

## 2. Estrutura de pastas

```
app/
  Models/, Servicos/, Http/, Support/, Console/   núcleo (as pastas de sempre do Laravel)
  Modulos/
    Modulo.php                base de todo módulo (carrega rotas, permissões, migrations; pontos de extensão)
    Modulos.php               lista dos módulos ligados; é por aqui que o núcleo fala com eles
    Estacionamento/
      EstacionamentoServiceProvider.php   o módulo (chave "estacionamento") e o que ele soma ao núcleo
      rotas.php               telas da gestão (pátio, estadias, lavagens, convênios, tabelas de estadia, etapas)
      rotas-api.php           API do PDV para estadia, lavagem, início e modo sem internet (/api/pdv/...)
      permissoes.php          patio_corrigir, convenios (entram nos grupos de config/permissoes.php)
      Models/                 Estadia, Tarifa (+ faixas, pacotes), Convenio, ConvenioRegra, EtapaLavagem, OrdemEtapa, PdvAcao
      Servicos/               Estadias, Tarifador, Tarifas (tabela vigente), Patio, Convenios, EtapasLavagem, Vendas,
                              Pdv/Lavagens, Pdv/SemInternet
      Http/Controllers/       Estadia, Lavagem, Patio, Convenio, Tarifa, EtapaLavagem; Pdv/ (EstadiaPdv, LavagemPdv, InicioPdv, SemInternet)
      migrations/             (quando houver) tabelas novas do módulo
config/modulos.php            módulos instalados
resources/js/
  Pages/, Components/, lib/, pdv/      núcleo
  Modulos/Estacionamento/
    Pages/                    Patio/, Convenios/, Configuracoes/ (Tarifas, TarifaForm, Etapas)
    Components/               AbasPatio, PatioLinha
    menu.js                   itens do menu e abas de Configurações do módulo
    pdv/telas/                Entrada, Saida, Estadias, EstadiaEntrada, EstadiaSaida, Lavagens
    pdv/offline/tarifador.js  tarifador do celular (modo sem internet)
tests/
  Feature/, Unit/             núcleo
  Modulos/Estacionamento/     testes do módulo (suíte "Modulos" no phpunit.xml)
```

**Ficaram no núcleo de propósito:** veículo, tipo de veículo, placa e mensalidade (o dinheiro do núcleo ainda depende deles; ver item 5), preço de serviço (`Precos`), caixa, Pix e pareamento do PDV.

**Migrations antigas** continuam em `database/migrations` (várias criam tabelas do núcleo e do estacionamento juntas e já rodaram em produção). Tabela nova de um módulo vai para `app/Modulos/<Nome>/migrations`.

## 3. Como o núcleo e os módulos conversam

- O núcleo **não chama classe de módulo direto**. Chama `App\Modulos\Modulos`, que pergunta a cada módulo ligado pelos pontos de extensão de `App\Modulos\Modulo`:

| Ponto | Para quê | Estacionamento usa para |
|---|---|---|
| `bloqueiosAnonimizar` | LGPD: o que impede anonimizar o cliente | veículo no pátio, lavagem em andamento, convênio ativo |
| `dadosPessoais` | LGPD: seções a mais na exportação | estadias do cliente |
| `anonimizar` | LGPD: apagar dados nas tabelas do módulo | placa, celular e retirada das estadias; dados das ações do PDV |
| `inicio` | dados a mais na tela Início | convênios a fechar |
| `compartilhado` | dados para todas as telas (contadores) | pendências do PDV sem internet |

- Ponto novo entra em `Modulo` (com resposta vazia por padrão) e em `Modulos`; o núcleo chama `Modulos::<ponto>()`.
- **Módulo pode usar o núcleo à vontade** (`Ordens`, `Baixa`, `Cobrancas`, `Cliente`…). O contrário não.
- **Telas:** o nome da tela de módulo é `<Nome>/Pages/...` (ex.: `Inertia::render('Estacionamento/Pages/Patio/Index')`). Componentes do núcleo são importados com `@/` (ex.: `import Campo from '@/Components/Campo'`).
- **Menu:** cada módulo tem `menu.js` com `MENU` (grupo e posição com `antes`/`depois`) e `CONFIGURACOES`; o `lib/menu.js` junta tudo e só mostra os itens dos módulos ligados (`props.modulos`).

## 4. Como criar um módulo novo (ex.: Agenda)

1. `app/Modulos/Agenda/AgendaServiceProvider.php` estendendo `App\Modulos\Modulo` (`chave()` = `agenda`, `nome()` = `Agenda`) e incluir a classe em `config/modulos.php`.
2. `rotas.php` / `rotas-api.php` com toda rota dentro de `permissao:<chave>` (ou `permissao:livre`), como em `routes/web.php`.
3. `permissoes.php` com as chaves do módulo.
4. `migrations/` com tabelas que têm `empresa_id` (e `unidade_id` quando acontece num lugar); modelos em `Models/` com `use PertenceAEmpresa;`.
5. Regra de negócio num **serviço único** em `Servicos/` (ex.: `App\Modulos\Agenda\Servicos\Agendamentos`: agendar, remarcar, cancelar, falta). Telas, API e PDV só chamam esse serviço.
6. **Dinheiro sempre pelo núcleo:** todo valor vira ordem de serviço por `Ordens`, recebido por `Baixa`, cobrado por `Cobrancas::emitir()`. O módulo não cria caminho de dinheiro próprio.
7. Telas em `resources/js/Modulos/Agenda/Pages`, menu em `resources/js/Modulos/Agenda/menu.js`.
8. Testes em `tests/Modulos/Agenda`, incluindo leitura de dados de outra empresa.
9. Se o módulo guarda dado pessoal: implementar `bloqueiosAnonimizar`, `dadosPessoais` e `anonimizar`.

## 5. O que ainda liga o núcleo ao estacionamento

Ficou de fora da reorganização por mexer em dinheiro ou no PDV sem ganho imediato. Resolver quando o primeiro produto novo começar:

**Dados (banco e dinheiro)**
- **Preço de serviço:** `servico_precos.tipo_veiculo_id` é obrigatório. Vira opcional, ou dá lugar a uma "variação de preço" genérica (no estacionamento, o tipo de veículo; no salão, o profissional, a duração ou o porte).
- **Mensalidade:** `mensalidades_contratos.veiculo_id` é obrigatório. Vira opcional, e o contrato passa a ser um plano ou assinatura do cliente.
- **Ordem de serviço:** colunas `estadia_id` e `convenio_id`, e os vínculos `OrdemServico::estadia()` / `convenio()` e `Cliente::convenio()` apontando para modelos do módulo. A ficha do cliente (`ClienteController`, resumo do convênio) e a NFS-e (número do ticket) usam esses vínculos.
- **Empresa:** ganha o campo de **ramo** (estacionamento, agenda, locação…), que define os módulos ligados e o modelo inicial (tipos, plano de contas, mensagens).

**Telas**
- `Inicio.jsx` desenha o bloco de convênios a fechar e `Clientes/DadosPessoais.jsx` o bloco de estadias (os dados já vêm do módulo; o desenho ainda está no núcleo).
- Ficha do cliente, cadastro público e recadastro falam de veículo e placa.
- **Termos da tela:** o [glossário](../00-glossario.md) ganha os termos por ramo (ex.: "pátio" e "veículo" só no estacionamento).

**PDV (celular)**
- `pdv/App.jsx` importa as telas do módulo direto; a tela Início do PDV, os modelos de impressão (ticket, recibos de estadia e lavagem, em `pdv/impressao/modelo.js`) e o modo sem internet (`pdv/offline/local.js`, que usa o tarifador do módulo) ainda misturam núcleo e estacionamento. Separar quando um segundo ramo usar o PDV.

**Outros**
- `lgpd:limpar` usa a última estadia para saber há quanto tempo o cliente não aparece (consulta direta na tabela `estadias`).
- Importação do sistema antigo do Local (`legado:importar`, `legado:comparar`): ferramenta só do Local, usa modelos do módulo.

## 6. Ordem

1. Terminar o SaaS de estacionamento (Fase 1 do plano em diante). Os ajustes do item 5 **não** entram antes de existir um produto novo de fato.
2. Até lá, regra de bolso: **não criar novas dependências de estacionamento no núcleo**. O que for só de estacionamento vai para `app/Modulos/Estacionamento`.
3. Ao começar um produto novo: ajustes do item 5, depois o módulo do ramo (item 4).

## 7. O que é núcleo e o que é de cada ramo (visão de produto)

| Núcleo (todos os produtos) | Só Estacionamento | Só Agenda / Locação (a criar) |
|---|---|---|
| Empresas, unidades, filtro por empresa | Pátio, estadias, tarifador | Profissionais e recursos (cadeira, sala, quadra, salão) |
| Usuários, níveis, permissões, log de atividades | Convênio com divisão (`Convenios::dividir()`) | Horário de trabalho, folgas e bloqueios |
| Clientes, cadastro público, LGPD | Etapas da lavagem | Serviço com duração, horário livre e conflito |
| Ordens de serviço, cobrança, baixa, estorno, caixa, extrato | Veículos e mensalidade por veículo (hoje no núcleo; ver item 5) | Agendamento online pelo cliente final (página pública por empresa) |
| Boleto e Pix do Inter, régua e modelos do WhatsApp | | Confirmação, lembrete, cancelamento e falta |
| NFS-e, contas a pagar, cartões, folha, resultado | | Sinal ou pagamento antecipado (Pix) |
| PDV no celular: pareamento, PIN, caixa, Pix, impressão | Telas do PDV de estadia e lavagem | Comissão por profissional, pacotes |
| | | Locação: reserva por período, caução, contrato, checklist de entrega |
