# Brief — dashboard detalhada de Leads

**Etapa:** 3 — Planejamento da dashboard de Leads  
**Status:** proposta consolidada para confirmação  
**Superfície planejada:** `/dashboard/leads`  
**Modo:** Operate

## Trabalho e público

Corretores e administradores acompanham a distribuição atual do pipeline e localizam os leads que compõem cada contagem. A análise deve oferecer leitura gerencial rápida e acesso direto às fichas operacionais dos registros.

## Métricas e referência temporal

- Exibir três indicadores principais, com contagens inteiras: **Proposta** = `proposta`; **Fechados** = `ganho` (rótulo real do sistema: “Ganho / Fechado”); **Perdidos** = `perdido`.
- Contar cada lead uma vez no seu estágio atual. Os demais estágios de `PipelineManager::STAGES` permanecem próprios: em especial, `negociacao` não entra em Proposta nem em Fechados. Reaberturas e retomadas refletem o estágio em que o lead está agora.
- A visão padrão é todo o histórico. O período opcional usa a data de criação `leads.created_at`; portanto, os números representam o estágio atual de leads criados no intervalo, mesmo que tenham mudado de estágio depois.
- As três contagens respondem ao período aplicado. A seleção de estágio filtra a lista abaixo, sem zerar os outros indicadores; assim o usuário continua comparando as três métricas enquanto examina um recorte.
- Intervalos de data respeitam `APP_TIMEZONE` e limites de dia inclusivos na data local. A implementação deve conferir a convenção dos timestamps já persistidos, conforme a ressalva da Etapa 0.

## Filtros e acesso

- Filtros combináveis: data inicial/final e estágio atual. Sem seleção, usar todo o histórico e todos os estágios.
- A Etapa 0 definiu acesso compartilhado e sem filtro por corretor para as dashboards. Portanto, esta superfície não terá filtro de corretor; a listagem operacional existente em `/leads` mantém seu filtro de responsável. A coluna de corretor pode aparecer na lista como informação do registro.
- Validar estágios contra `PipelineManager::STAGES` e datas antes da consulta. Filtro inválido deve ser informado e desconsiderado sem ampliar acesso ou gerar erro de consulta.
- Enviar filtros por GET para permitir URLs compartilháveis; preservar filtros na paginação e retornar à página 1 quando um filtro mudar.

## Hierarquia, navegação e interação

- Composição escolhida: filtros compactos no topo, três cartões de métricas em uma faixa e lista paginada abaixo. Cada cartão mostra rótulo, número e estágio mapeado.
- Selecionar um cartão de Proposta, Fechados ou Perdidos aplica aquele estágio à lista e preserva o período. O seletor geral de estágio oferece os oito valores reais do pipeline.
- A lista exibe até 15 leads por página, ordenados por criação mais recente. Colunas: nome do lead, estágio atual, corretor responsável, data de criação e ação para abrir a ficha. Cada linha abre `/leads/{id}`.
- O resumo principal poderá abrir `/dashboard/leads` com a etapa correspondente pré-selecionada, preservando a regra das contagens do pipeline e o período padrão.
- A rota `/dashboard/leads` ainda está planejada. As rotas `/leads` e `/leads/{id}` já existem; o destino escolhido mantém a lista filtrada na dashboard porque a listagem operacional atual não aceita período por criação.
- Em telas largas, manter as três métricas na mesma faixa e a tabela com seus rótulos e colunas. Em telas estreitas, empilhar ou quebrar os cartões e adaptar a tabela ao padrão responsivo do módulo, sem separar valores de seus rótulos.
- Reutilizar filtros, cartões, badges, foco e feedback HTMX do CRM; não introduzir uma linguagem visual nova para esta superfície.

## Estados

- Sem leads na base: indicadores mostram zero e a lista explica que ainda não há leads, com ação para iniciar cadastro em `/leads/new`.
- Período sem registros ou combinação de filtros sem resultados: indicadores refletem zero para o período; a lista mostra mensagem própria e ação para limpar filtros.
- Falha nos indicadores ou na consulta da lista: aviso local e tentativa novamente no bloco afetado, mantendo a outra área disponível. Atualizações HTMX indicam carregamento na região atualizada; primeira renderização é server-side.
- Página, filtros, cartões e paginação mantêm foco visível e uso por teclado. Estágios devem ter rótulos além de cor e conservar os badges usados pelo módulo existente.

## Dados e consultas previstas

- Fonte das métricas e da lista: tabela `leads`; estágio em `leads.stage`, período em `leads.created_at`; corretor para exibição por relação com `users`.
- Agregar contagem por estágio para o período, sem aplicar o filtro de estágio selecionado aos três indicadores. Consultas parametrizadas e executadas no servidor.
- Aplicar período e, para a lista, estágio atual selecionado; paginar a lista no servidor. A implementação poderá estender `LeadRepository::countByStage()` e `paginate()` ou criar métodos de dashboard. Hoje a contagem não recebe filtros e a paginação não aceita data de criação.
- O filtro não muda o estágio dos registros nem interpreta o audit log como histórico analítico. O banco não mantém uma tabela estruturada de transições para conversão histórica.
- Implementação planejada em PHP/Slim, SQLite, templates PHP e HTMX, seguindo a composição existente de cartões, filtros e tabelas do CRM.

## Confirmação

Confirme ou corrija este brief para encerrar a Etapa 3.
