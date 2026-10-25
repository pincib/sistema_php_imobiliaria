---
target: rota principal e rotas detalhadas
total_score: 28
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 1
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\dashboard"
timestamp: 2026-10-25T01-45-30Z
slug: views-dashboard
closed: true
---
Method: dual-agent (A: design_review · B: detector_evidence)

# Crítica — `views/dashboard`

## Design Health Score

| # | Heurística | Nota | Questão principal |
|---|---|---:|---|
| 1 | Visibilidade do estado do sistema | 3/4 | Estados de erro e repetição por bloco; sem inspeção visual autenticada |
| 2 | Correspondência com o mundo real | 4/4 | Vocabulário imobiliário e valores em reais claros |
| 3 | Controle e liberdade | 3/4 | Filtros compartilháveis e opção de limpar; falta retorno direto ao padrão |
| 4 | Consistência e padrões | 3/4 | Padrões comuns; a navegação só destaca o módulo, não a análise atual |
| 5 | Prevenção de erros | 3/4 | Campos e validações claros; escopo da faixa de valor dos proprietários pode confundir |
| 6 | Reconhecimento em vez de memorização | 3/4 | Rótulos e explicações locais ajudam a interpretar filtros e métricas |
| 7 | Flexibilidade e eficiência | 2/4 | Links diretos e paginação ajudam; sem filtros salvos ou atalhos |
| 8 | Estética e minimalismo | 3/4 | Composição contida; tela de Proprietários concentra mais controles e rankings |
| 9 | Reconhecimento e recuperação de erros | 3/4 | Erros localizados e repetição por bloco; filtros inválidos precisam de leitura adicional |
| 10 | Ajuda e documentação | 1/4 | Há explicações inline, mas não ajuda contextual ou documentação acessível |
| **Total** |  | **28/40** | **Bom** |

## Veredito de especificidade

As telas refletem o CRM imobiliário por meio da agenda de follow-ups, estágios do pipeline, vínculos com imóveis e proprietários, valores em reais e idade do contato confirmado. Os padrões visuais são familiares e pouco distintos, o que combina com o produto operacional descrito em `PRODUCT.md`. A avaliação foi baseada no código: as rotas redirecionaram para login e não foi feita autenticação, então a hierarquia visual renderizada não pôde ser confirmada.

O detector `impeccable detect --json views/dashboard` retornou `[]`, código 0: nenhum achado, regra ou localização. Não houve falso positivo. As abas abriram `/` e as três rotas detalhadas; todas levaram a `/login`.

Não foi possível injetar o overlay, pois a API de navegador disponível permite apenas avaliação somente leitura. Como a visualização autenticada também não estava acessível, não há overlay visível nem leitura de console a reportar. O sinal alternativo é a ausência de achados no detector e o redirecionamento para login.

## Impressão geral

O conjunto está alinhado ao uso diário de uma equipe comercial: a agenda continua central e as métricas levam às listas que as explicam. O maior ponto de atrito está nos filtros da dashboard de Proprietários, em que duas faixas de valor atuam independentemente em rankings diferentes, mas podem parecer uma única medida combinada.

## O que funciona

- A ordem da página principal preserva follow-ups e depois organiza Leads, Imóveis e Proprietários.
- As definições das métricas — período por criação do lead, estágio atual, vínculos distintos, soma por intenção e contato confirmado — aparecem junto às análises.
- Os números têm caminhos para as evidências de origem; erros e estados vazios são tratados por área, sem remover a agenda.

## Problemas prioritários

1. **[P1] Escopo da faixa de valor de Proprietários pode ser interpretado como uma soma combinada.** Os rankings de venda e aluguel são independentes, mas usam limites mínimos e máximos com rótulos compartilhados. A pessoa pode esperar que a faixa se aplique a uma soma única ou apenas ao ranking que está lendo. Explicitar que cada limite é aplicado separadamente ao total de venda e ao total de aluguel; se a operação precisar de limites diferentes, oferecer controles por ranking. **Comando:** `$impeccable clarify`.
2. **[P2] As páginas de Imóveis e Proprietários pedem várias dimensões de filtro de uma vez.** Período, tipo, situação e estágio (até oito opções) aumentam o esforço antes da leitura dos resultados. Agrupar filtros por período, imóvel e lead; avaliar recolher os menos usados, mantendo estado na URL. **Comando:** `$impeccable distill`.
3. **[P2] Estado vazio de Proprietários não diferencia base sem vínculos de filtro sem correspondências.** Sem filtros, “Nenhum proprietário corresponde aos filtros” pode sugerir que algum filtro excluiu dados. Distinguir “não há proprietários com imóveis vinculados” de “nenhum resultado para estes filtros” e manter a ligação para `/owners`. **Comando:** `$impeccable clarify`.
4. **[P2] A navegação principal indica o módulo, mas não a análise aberta.** Depois de abrir uma dashboard detalhada ou rolar a página, o módulo ativo não informa a subseção. Adicionar uma indicação leve, como “Dashboard › Análise de Leads”, preservando a navegação principal simples. **Comando:** `$impeccable polish`.

## Sinais por perfil

- **Alex, usuário frequente:** não há filtros salvos ou atalhos visíveis; análises recorrentes exigem refazer seleções. Links e paginação facilitam o detalhamento.
- **Sam, teclado/leitor de tela:** o código indica títulos semânticos, rótulos, cabeçalhos de tabela, foco e áreas de status. Ordem de foco, contraste e anúncios do leitor de tela não foram verificados em tela autenticada.
- **Jordan, primeira utilização:** os textos são em português direto e explicam as métricas; o intervalo de valor compartilhado em Proprietários continua ambíguo.

## Carga cognitiva

**3 falhas no checklist: carga moderada.** Há agrupamento e explicações que reduzem memorização. Porém, as páginas exibem várias dimensões simultâneas e o seletor de estágio pode mostrar oito opções; na tela de Proprietários, filtros e três rankings aparecem juntos. Recomenda-se agrupar e revelar filtros menos usados progressivamente, sem retirar dimensões analíticas aprovadas.

## Observações menores

- A página de Proprietários exibe a quantidade de dias desde contato como número sem unidade (“dias”); a página principal já apresenta unidade.
- Na tabela de Imóveis, vários registros sem código podem aparecer com o mesmo início “Sem código”.
- A navegação administrativa em linha pode ficar apertada em notebooks; precisa de confirmação na renderização.
- “Alguns filtros foram ignorados” exige ler os detalhes para entender quais filtros falharam.

## Perguntas para considerar

- Os limites de valor de venda e aluguel devem permanecer compartilhados ou precisam ser configuráveis por ranking?
- Quais filtros de Imóveis são usados diariamente e poderiam ficar sempre visíveis, com os demais recolhidos?
- Para o estado inicial sem proprietários vinculados, a mensagem deve direcionar diretamente à lista `/owners`?
