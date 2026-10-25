# Roadmap — Dashboards gerenciais do CRM imobiliário

## 1. Objetivo

Expandir a dashboard atual para oferecer uma visão gerencial mais completa da operação imobiliária, mantendo a página principal como um resumo rápido. Cada área do sistema terá uma dashboard detalhada, com filtros, rankings e acesso aos registros que formam cada indicador.

A solução deve respeitar a arquitetura existente: PHP 8, Slim 4, SQLite, templates PHP nativos, HTMX e os padrões visuais já usados pelo produto. As consultas devem ser executadas no servidor e os dados exibidos devem poder ser conferidos nos registros de origem.

## 2. Estado atual e contexto

- A dashboard principal já apresenta a agenda de follow-ups do dia, atrasados e futuros.
- A dashboard tem links para os módulos de Leads, Imóveis e Placas.
- O controller da dashboard já reúne dados operacionais e renderiza a página principal.
- O projeto já possui dados e módulos para leads, imóveis, proprietários e placas.
- Imóveis possuem tipo, preço de venda, preço de aluguel e associações com leads.
- Proprietários são relacionados a imóveis por uma tabela de vínculo.
- O pipeline de leads possui etapas implementadas. Os rótulos de proposta, fechado e perdido devem ser associados às etapas reais do pipeline, sem criar estados paralelos.
- A nova camada gerencial deve complementar os fluxos operacionais, sem substituir a agenda de follow-ups.

## 3. Princípios da solução

1. **Resumo primeiro:** a dashboard principal apresenta apenas indicadores-chave e pequenos rankings.
2. **Detalhe sob demanda:** cada bloco da página principal leva à dashboard detalhada da área correspondente.
3. **Rastreabilidade:** todo indicador deve permitir chegar aos registros que o compõem, quando aplicável.
4. **Definições explícitas:** cada métrica deve ter uma regra escrita, para evitar que o mesmo rótulo represente cálculos diferentes em telas diferentes.
5. **Compatibilidade com o produto:** manter renderização no servidor, HTMX e componentes leves, sem introduzir uma SPA ou dependências frontend pesadas.
6. **Consistência de acesso:** respeitar as permissões atuais e não revelar dados que o usuário não poderia consultar nos módulos de origem.
7. **Desempenho previsível:** usar agregações SQL, índices pertinentes e limites para rankings; evitar carregar todas as entidades para calcular métricas em PHP.
8. **Estados completos:** projetar estados com dados, sem dados, erro, filtros sem resultado e carregamento quando a interação exigir atualização parcial.

## 4. Escopo funcional

### 4.1 Dashboard principal — resumo gerencial

A dashboard principal manterá a agenda operacional de follow-ups e acrescentará os seguintes resumos:

#### Imóveis

- **Imóveis com mais leads:** mostrar um ranking curto dos imóveis com maior número de leads associados.
- **Tipos de imóveis com mais leads:** agrupar os leads associados por tipo de imóvel, por exemplo loja, terreno e sala.
- Cada resumo deve oferecer um link para abrir a dashboard detalhada de Imóveis.

#### Leads

- Exibir a quantidade de leads em **Proposta**, **Fechados** e **Perdidos**.
- Cada indicador deve estar associado aos estágios reais e às regras de transição já definidas para o pipeline.
- Cada indicador deve permitir abrir a dashboard detalhada de Leads com o filtro correspondente aplicado, se a navegação atual permitir esse comportamento.

#### Proprietários

- **Proprietários com mais imóveis:** ranking curto ordenado pela quantidade de imóveis vinculados.
- **Proprietários mais valiosos:** apresentar rankings separados para venda e aluguel, usando os valores dos imóveis vinculados.
- **Último contato:** exibir somente o valor numérico acordado para esse indicador, sem nome na área de resumo. A definição exata do número deve ser confirmada antes da implementação.
- Cada resumo deve oferecer um link para a dashboard detalhada de Proprietários.

#### Regras para o resumo

- Definir um limite pequeno e consistente para os rankings, por exemplo os cinco primeiros. O limite final pode ser ajustado durante o planejamento da interface.
- Exibir rótulos e unidades claros, especialmente nos valores monetários e nas contagens.
- Indicar a data ou o período ao qual os indicadores se referem quando houver filtro temporal.
- Não duplicar tabelas extensas, filtros avançados ou históricos completos na página principal.
- Preservar o acesso rápido à criação de lead e aos follow-ups.

### 4.2 Dashboard detalhada de Imóveis

A dashboard de Imóveis permitirá analisar a relação entre imóveis e leads.

**Conteúdo previsto:**

- Ranking de imóveis por quantidade de leads associados.
- Distribuição de leads por tipo de imóvel.
- Acesso ao detalhe do imóvel e à lista de leads vinculados.
- Filtros aprovados para período, tipo, situação do imóvel e/ou estágio do lead, conforme a definição das métricas.
- Paginação ou limite de resultados nos rankings extensos.
- Indicação explícita quando não houver imóvel com lead associado ou quando o filtro não retornar resultados.

**Regra a definir:** decidir se o ranking conta todos os vínculos históricos ou somente leads ativos. Também definir como tratar associações removidas, imóveis indisponíveis e múltiplos vínculos do mesmo lead.

### 4.3 Dashboard detalhada de Leads

A dashboard de Leads permitirá consultar a distribuição do pipeline e navegar até os registros correspondentes.

**Conteúdo previsto:**

- Totais de leads em Proposta, Fechados e Perdidos.
- Distribuição por estágio, com proporções somente se o denominador for informado e estiver claramente definido.
- Filtros por período e, se permitido pelo modelo de acesso, por corretor.
- Acesso à listagem de leads já filtrada por etapa.
- Exibição coerente com o pipeline implementado e com os motivos obrigatórios de perda.

**Regra a definir:** especificar se o período usa a data de criação do lead, a data de entrada no estágio atual ou a data em que o estágio foi alterado. Para análises de conversão, a data de mudança de estágio tende a ser mais informativa, mas depende da disponibilidade e qualidade do histórico.

### 4.4 Dashboard detalhada de Proprietários

A dashboard de Proprietários permitirá analisar quantidade de imóveis, valores e contato.

**Conteúdo previsto:**

- Ranking de proprietários pela quantidade de imóveis vinculados.
- Ranking de proprietários pelo valor de venda, separado do ranking de aluguel.
- Acesso aos imóveis que compõem cada total.
- Indicador de contato conforme a definição aprovada, com mais detalhe nesta área se o fluxo e as permissões permitirem.
- Filtros por tipo de negócio, faixa de valor, tipo de imóvel e período, quando aplicáveis.

**Regras a definir:**

- Confirmar se “mais valioso” significa maior imóvel individual ou soma dos valores dos imóveis do proprietário. Recomenda-se usar a soma como ranking principal e deixar claro o critério.
- Definir o tratamento para imóveis sem preço, com valor zero ou com intenção de venda e aluguel.
- Confirmar o que representa “Último contato de proprietários (apenas mostre o número)”: pode ser uma contagem de dias desde o último contato, uma quantidade de proprietários sem contato dentro de um período ou o número de telefone do último contato. Não implementar esse indicador antes de registrar a definição escolhida.

### 4.5 Dashboard detalhada de Placas

O pedido não especifica métricas para placas, mas a navegação deve manter uma área de dashboard detalhada do módulo, caso essa aba seja incluída junto às demais.

**Conteúdo inicial sugerido, sujeito à validação operacional:**

- Contagem de placas por situação operacional já prevista no sistema.
- Lista das placas que exigem atenção conforme as regras existentes.
- Acesso ao imóvel vinculado e ao histórico da placa.

Não criar novos indicadores ou estados de negócio sem validar sua utilidade e sem respeitar o modelo já implementado para placas.

## 5. Definições de métricas — checklist obrigatório

Antes de implementar consultas, registrar as definições abaixo em um brief ou decisão de produto:

| Métrica | Decisão necessária |
|---|---|
| Imóveis com mais leads | Contar vínculos atuais ou históricos; incluir todos os estágios ou apenas leads ativos; tratar duplicidade de vínculos. |
| Tipos com mais leads | Definir a origem e normalização dos tipos; esclarecer como agrupar tipos ausentes ou variantes de escrita. |
| Proposta | Associar o rótulo ao estágio real do pipeline. |
| Fechados | Definir quais estados contam como negócio fechado. |
| Perdidos | Confirmar o estágio de perda e o tratamento de descartes ou outros encerramentos. |
| Período dos leads | Definir se filtra por criação, atualização ou evento de mudança de estágio. |
| Proprietários com mais imóveis | Definir se imóveis inativos ou removidos entram na contagem. |
| Proprietários mais valiosos | Definir soma dos valores ou maior imóvel; explicitar tratamento de valores ausentes. |
| Valores de venda e aluguel | Manter rankings separados e definir formatação monetária em BRL. |
| Último contato | Definir o significado exato do número exibido e a fonte da data/telefone/contagem. |
| Período padrão | Definir se a visão inicial é total histórico, mês atual ou outro intervalo. |
| Permissões | Confirmar quem pode ver os rankings e quais filtros por usuário são permitidos. |

## 6. Roadmap por etapas

### Etapa 0 — Levantamento e decisões de produto

**Objetivo:** fechar as definições das métricas antes de projetar consultas e telas.

**Atividades:**

- Conferir os estágios do pipeline e documentar o mapeamento para proposta, fechado e perdido.
- Definir o período padrão e a semântica temporal dos indicadores.
- Decidir o cálculo de imóveis com mais leads e tipos com mais leads.
- Decidir o cálculo de valor dos proprietários, separando venda e aluguel.
- Resolver o significado do indicador de último contato.
- Confirmar filtros, permissões e limite inicial dos rankings.
- Registrar decisões e dúvidas que permanecerem abertas.

**Comando Impeccable:**

```text
$impeccable shape métricas-e-regras-das-dashboards
```

**O que o comando deve produzir:** um brief sem código que consolide público, objetivo, dados, definições das métricas, filtros, estados e limites. Como a maior parte das decisões de negócio não é definida pela skill, valide os pontos operacionais com os responsáveis pelo CRM.

**Critério de saída:** todas as métricas solicitadas possuem regra escrita, ou estão marcadas como bloqueadas por uma decisão identificada.

### Etapa 1 — Planejamento da dashboard principal

**Objetivo:** definir a hierarquia do resumo e a navegação para os detalhes.

**Atividades:**

- Preservar a agenda de follow-ups como função central da dashboard atual.
- Definir a ordem dos blocos de Imóveis, Leads e Proprietários.
- Definir quantidade de linhas/itens em cada ranking.
- Definir links do resumo para as dashboards detalhadas e filtros iniciais.
- Definir estados de vazio, falha e dados parciais.
- Manter consistência com os componentes e padrões atuais do sistema.

**Comando Impeccable:**

```text
$impeccable shape resumo-gerencial-da-dashboard-principal
```

**O que o comando deve produzir:** brief de UX para a área principal, incluindo hierarquia, densidade, navegação, responsividade e estados. O brief deve tratar a página como superfície operacional: informação rápida e ações frequentes devem continuar fáceis de localizar.

**Critério de saída:** layout e comportamento do resumo definidos antes de alterar templates ou controller.

### Etapa 2 — Planejamento da dashboard de Imóveis

**Objetivo:** definir como explorar leads por imóvel e por tipo.

**Atividades:**

- Planejar ranking, comparação por tipo e acesso ao detalhe.
- Definir filtros e combinação com estágios do lead.
- Definir comportamento quando não houver vínculos ou tipos válidos.
- Identificar consultas e relacionamentos necessários no banco.

**Comando Impeccable:**

```text
$impeccable shape dashboard-detalhada-de-imóveis
```

**O que o comando deve produzir:** brief para uma superfície detalhada de Imóveis, incluindo filtros, hierarquia dos rankings, navegação até leads e estados da tela.

**Critério de saída:** as regras do ranking são compatíveis com as decisões da Etapa 0 e os destinos de navegação existem ou estão planejados.

### Etapa 3 — Planejamento da dashboard de Leads

**Objetivo:** definir a leitura do pipeline e o acesso às listas filtradas.

**Atividades:**

- Mapear os três indicadores para os estágios reais.
- Definir o período e a data de referência das consultas.
- Planejar filtros por período, corretor e estágio, conforme permissão.
- Planejar acesso à lista e tratamento de estados sem resultado.

**Comando Impeccable:**

```text
$impeccable shape dashboard-detalhada-de-leads
```

**O que o comando deve produzir:** brief com hierarquia das métricas do pipeline, filtros, navegação e regras de interação, mantendo consistência com a máquina de estados existente.

**Critério de saída:** cada estado mostrado corresponde a uma definição inequívoca do pipeline.

### Etapa 4 — Planejamento da dashboard de Proprietários

**Objetivo:** definir rankings por quantidade e valor e resolver a apresentação do contato.

**Atividades:**

- Planejar ranking por número de imóveis.
- Planejar rankings separados de venda e aluguel.
- Definir se o valor é soma ou maior imóvel individual.
- Registrar a regra para preço ausente ou zero.
- Definir e planejar o indicador de último contato.
- Planejar acesso ao detalhe do proprietário e aos imóveis considerados.

**Comando Impeccable:**

```text
$impeccable shape dashboard-detalhada-de-proprietários
```

**O que o comando deve produzir:** brief com rankings separados, unidades claras, filtros e navegação. O indicador de último contato só deve entrar no brief final depois que seu significado numérico for aprovado.

**Critério de saída:** o ranking pode ser explicado e reproduzido a partir dos registros de origem.

### Etapa 5 — Planejamento da dashboard de Placas

**Objetivo:** decidir se a área terá uma visão gerencial própria e quais informações operacionais são necessárias.

**Atividades:**

- Validar com a operação quais perguntas precisam ser respondidas sobre placas.
- Reaproveitar estados e regras já existentes.
- Planejar links para imóvel e histórico de movimentações.
- Evitar criar novos estados ou métricas sem necessidade confirmada.

**Comando Impeccable:**

```text
$impeccable shape dashboard-detalhada-de-placas
```

**O que o comando deve produzir:** brief enxuto para a superfície de Placas, caso sua construção seja confirmada. O brief deve refletir os estados e permissões já existentes.

**Critério de saída:** a área possui um objetivo operacional confirmado e não duplica a listagem do módulo sem oferecer valor gerencial.

### Etapa 6 — Preparação de dados e implementação backend

**Objetivo:** entregar dados consistentes para o resumo e as páginas detalhadas.

**Atividades:**

- Revisar o esquema SQLite, índices e serviços/repositórios existentes para as entidades necessárias.
- Criar consultas agregadas parametrizadas no servidor, separadas por domínio e fáceis de auditar.
- Evitar SQL montado com valores de filtros concatenados; parâmetros devem ser vinculados.
- Definir limites para rankings e paginação para listas detalhadas.
- Garantir que filtros de data usem o fuso local definido pela aplicação.
- Verificar que junções não multipliquem contagens indevidamente, especialmente em relações muitos-para-muitos.
- Usar índices apenas quando sustentados pelas consultas e pelo volume esperado.
- Não retornar dados pessoais desnecessários para um gráfico ou resumo.
- Preservar as permissões dos módulos de origem.

**Comandos Impeccable:**

```text
Nenhum comando Impeccable é necessário para tarefas exclusivamente de backend, SQL ou banco de dados.
```

**Critério de saída:** cada consulta tem regra documentada, usa parâmetros e produz resultados reconciliáveis com os dados de origem.

### Etapa 7 — Implementação das telas e navegação

**Objetivo:** construir o resumo principal e as dashboards detalhadas seguindo os briefs aprovados.

**Atividades:**

- Implementar blocos resumidos na dashboard principal.
- Implementar as páginas detalhadas por domínio.
- Adicionar navegação do resumo para detalhes e filtros quando aplicável.
- Usar templates PHP e HTMX conforme os padrões existentes.
- Implementar feedback de carregamento em atualizações parciais, erros legíveis e estados vazios.
- Garantir acessibilidade por teclado, semântica de títulos, rótulos e contraste.
- Manter gráficos simples e legíveis; tabelas podem ser preferíveis quando o usuário precisa comparar valores exatos ou abrir registros.
- Garantir que a informação não dependa apenas de cor para comunicar estágio ou situação.

**Comandos Impeccable depois de existir um alvo real:**

```text
$impeccable critique <arquivo-ou-rota-real>
$impeccable audit <arquivo-ou-rota-real>
$impeccable harden <arquivo-ou-rota-real>
$impeccable polish <arquivo-ou-rota-real>
```

**O que cada comando deve fazer:**

- `critique`: avaliar a clareza da experiência, hierarquia, navegação e entendimento dos indicadores.
- `audit`: apontar problemas técnicos de acessibilidade, responsividade e qualidade verificável da interface.
- `harden`: revisar limites, mensagens, filtros, erros, permissões, dados ausentes e estados extremos.
- `polish`: executar uma revisão final do acabamento visual e da consistência da superfície.

Substitua `<arquivo-ou-rota-real>` pelo caminho que existir no projeto. Os comandos de avaliação/refino devem ser aplicados após a tela ter implementação real, não a um caminho inventado.

**Critério de saída:** resumo e detalhes funcionam com dados reais, links preservam o contexto esperado e estados importantes foram implementados.

### Etapa 8 — Revisão de responsividade e estados extremos

**Objetivo:** assegurar que dashboards com rankings e filtros continuem utilizáveis em larguras menores e com dados incomuns.

**Atividades:**

- Revisar tabelas largas, títulos extensos, valores grandes e ausência de nomes.
- Revisar filtros combinados e consulta sem resultado.
- Confirmar apresentação de zero, nulo, preço não cadastrado e proprietários sem imóveis.
- Confirmar que os cards não ocultam as informações essenciais em telas menores.
- Avaliar navegação por teclado e foco visível.

**Comandos Impeccable:**

```text
$impeccable adapt <arquivo-ou-rota-real>
$impeccable harden <arquivo-ou-rota-real>
```

**O que os comandos devem fazer:** `adapt` orienta a revisão de diferentes larguras e contextos de uso; `harden` ajuda a fechar estados de borda, valores ausentes e comportamento de filtros. Use `adapt` quando telas menores estiverem dentro do escopo do produto.

**Critério de saída:** conteúdo continua legível, operável e compreensível nos tamanhos previstos para uso.

### Etapa 9 — Fechamento e validação dos indicadores

**Objetivo:** validar a coerência entre resumo, dashboards detalhadas e registros originais.

**Atividades:**

- Conferir cada total com consultas/listas de origem.
- Conferir o resultado dos filtros e limites dos rankings.
- Revisar acesso com os papéis de usuário pertinentes.
- Confirmar navegação de cada card para o domínio correspondente.
- Resolver achados das avaliações Impeccable.
- Atualizar este roadmap ou a documentação do produto caso as regras aprovadas mudem.

**Comandos Impeccable:**

```text
$impeccable critique <rota-principal-e-rotas-detalhadas>
$impeccable audit <rota-principal-e-rotas-detalhadas>
$impeccable polish <rota-principal-e-rotas-detalhadas>
```

**O que os comandos devem fazer:** revisar as superfícies entregues em conjunto, verificar consistência entre navegação e apresentação e fechar o acabamento após corrigir os achados.

**Critério de saída:** indicadores são rastreáveis, acessos estão coerentes e as superfícies estão prontas para uso operacional.

## 7. Convenção de uso dos comandos Impeccable

No começo de cada sessão de trabalho visual, execute `context` na raiz do projeto. Assim que o arquivo ou rota existir, passe o alvo específico para carregar o contexto correspondente:

```powershell
& 'C:\Users\berna\.agents\skills\impeccable\scripts\impeccable.cmd' context --target <arquivo-ou-rota-real>
```

Os comandos da skill são invocados durante a conversa com o agente como `$impeccable <comando>`. `context` e `detect` também podem ser executados pelo launcher local. Para uma alteração exclusivamente de interface, use `detect --json` nos arquivos modificados quando o fluxo de trabalho do projeto indicar essa verificação e não houver detector automático ativo.

A sequência típica de uma nova superfície é:

1. `context --target <alvo-real>` no início da sessão visual.
2. `shape <assunto-da-superfície>` para planejar antes da implementação.
3. Implementar a superfície no código do projeto.
4. `critique <alvo-real>` para avaliar a experiência.
5. `audit <alvo-real>` para revisar qualidade técnica da interface.
6. `harden <alvo-real>` quando existirem filtros, permissões, erros ou dados extremos.
7. `adapt <alvo-real>` quando telas menores forem parte do escopo.
8. `polish <alvo-real>` para o acabamento final.

Não executar comandos de avaliação sobre alvos que ainda não existam. Para backend, SQL, consultas e mudanças de banco de dados sem alteração de interface, não há comando Impeccable obrigatório.

## 8. Critérios de aceite do roadmap

- A dashboard principal preserva os follow-ups e apresenta os resumos de Imóveis, Leads e Proprietários solicitados.
- Cada resumo leva à dashboard detalhada da área correspondente.
- A dashboard de Imóveis apresenta imóveis com mais leads e tipos com mais leads.
- A dashboard de Leads apresenta proposta, fechados e perdidos conforme o pipeline real.
- A dashboard de Proprietários apresenta ranking por quantidade de imóveis, rankings de valor separados para venda e aluguel e o indicador numérico de último contato após sua definição.
- As métricas detalhadas permitem identificar os registros de origem.
- Os filtros e períodos têm significado documentado.
- As consultas respeitam acesso, tratam relações múltiplas sem duplicar contagens e usam parâmetros.
- Estados vazios, erros, valores ausentes e filtros sem resultados são tratados.
- A interface continua coerente com a arquitetura e os padrões do produto.
- As avaliações Impeccable pertinentes foram executadas sobre arquivos ou rotas existentes e seus achados foram resolvidos ou documentados.

## 9. Decisões pendentes antes da implementação

1. O indicador “Último contato de proprietários” deve mostrar dias desde o contato, quantidade de proprietários sem contato, telefone do contato mais recente ou outro número?
2. “Proprietários mais valiosos” deve ordenar pela soma dos imóveis ou pelo maior imóvel individual?
3. O ranking de imóveis deve incluir todos os leads associados ou apenas os leads ativos?
4. A contagem de leads por etapa deve refletir o estado atual ou as transições ocorridas dentro de um período?
5. Qual é o período padrão das dashboards: total histórico, mês atual ou outro intervalo?
6. Quem pode consultar rankings e aplicar filtros por corretor?
7. A dashboard detalhada de Placas faz parte da primeira entrega ou deve ficar para uma etapa posterior?

