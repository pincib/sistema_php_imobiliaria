# Brief — dashboard detalhada de Proprietários

**Etapa:** 4 — Planejamento da dashboard de Proprietários  
**Status:** proposta consolidada para confirmação  
**Superfície planejada:** `/dashboard/owners`  
**Modo:** Operate

## Trabalho e público

Corretores e administradores comparam proprietários por quantidade de imóveis vinculados e valor cadastrado, conferem quais imóveis formam cada resultado e acessam os cadastros operacionais existentes.

## Rankings e unidades

- **Quantidade:** contar imóveis distintos atualmente vinculados a cada proprietário em `owner_properties`; exibir número seguido da unidade “imóveis”. Vínculos removidos não contam; após os filtros, incluir no ranking somente proprietários com ao menos um imóvel correspondente. Todos os status são incluídos por padrão.
- **Venda:** somar `sale_price` dos imóveis atualmente vinculados cuja intenção seja `venda` ou `venda_locacao`. Exibir a soma em reais (BRL).
- **Aluguel:** somar `rent_price` dos imóveis atualmente vinculados cuja intenção seja `locacao` ou `venda_locacao`. Exibir a soma em reais (BRL).
- Os rankings de venda e aluguel são separados e descendentes pela soma do proprietário; em empate, ordenar pelo nome do proprietário. Um imóvel `venda_locacao` pode contribuir para os dois rankings.
- Preço nulo significa não informado e não contribui. Preço zero é válido; se for o único preço elegível, o proprietário aparece com **R$ 0,00**. Proprietários sem imóvel elegível ou sem preço cadastrado não entram no ranking de valor.
- Cada ranking mostra até 15 proprietários por página, com paginação própria. O padrão cobre todos os vínculos atuais, todos os status, tipos e modalidades, sem filtro de período ou corretor. Proprietários sem imóvel atual permanecem acessíveis pela listagem operacional, fora dos rankings.

## Filtros

- Tipo e situação do imóvel são filtros compartilhados pelos três rankings. Sem seleção, todos os tipos e situações entram, incluindo vendido, alugado e inativo.
- A modalidade recorta o ranking de quantidade; sem seleção, entram imóveis de todas as intenções.
- A faixa de valor filtra o total agregado por proprietário nos rankings de venda e aluguel, não o preço de cada imóvel antes da soma. O valor é interpretado na unidade do ranking (venda ou aluguel).
- Os rankings monetários mantêm suas intenções próprias: imóveis de venda ou venda/locação contribuem para venda; imóveis de locação ou venda/locação contribuem para aluguel. A modalidade do filtro de quantidade não mistura nem troca essas regras.
- Filtros podem ser combinados, são enviados por GET e permanecem ao paginar. Mudá-los retorna o painel à primeira página. Filtros inválidos devem ser informados e desconsiderados com segurança.
- A Etapa 0 define acesso compartilhado, sem filtro por corretor. Nenhum ranking depende do usuário que vinculou o imóvel ou registrou o contato.

## Último contato confirmado

- Exibir um indicador geral: **dias corridos desde o registro confirmado mais recente de contato com qualquer proprietário**, sem associar o nome de um proprietário ao indicador.
- Considerar `whatsapp_contact_logs` onde `contact_type = 'owner'` e `result = 'confirmado pelo corretor'`. Registros “não enviado” e “cancelado” ficam fora.
- O número é a diferença, em dias corridos, entre a data local atual e a data local de `created_at` do registro mais recente; no mesmo dia, mostrar 0. Esse timestamp é a hora em que o resultado foi registrado no sistema, não uma hora separada do contato realizado.
- O indicador é geral e independente dos filtros de imóvel e das seleções de ranking. Se não existir contato confirmado, mostrar **—**.
- Datas devem respeitar `APP_TIMEZONE` e a convenção dos timestamps persistidos. A Etapa 0 registra que essa métrica usa o horário do registro como aproximação do contato.

## Hierarquia, navegação e interação

1. Cabeçalho **Análise de Proprietários**, retorno ao resumo principal e contexto dos filtros ativos.
2. Indicador geral de dias desde o contato confirmado mais recente.
3. Filtros compartilhados de tipo/situação, filtro de modalidade do ranking por quantidade e faixa monetária por ranking de valor.
4. Ranking por quantidade de imóveis.
5. Rankings de valor de venda e aluguel em painéis separados, lado a lado em telas largas e empilhados em telas estreitas. Cada painel mantém seu título, unidade e paginação.

- Cada linha identifica proprietário e métrica. O nome abre a ficha existente `/owners/{id}`, que lista seus imóveis vinculados.
- A métrica abre a análise planejada de contribuição em `/dashboard/owners?owner_id={id}&ranking={count|sale|rent}`, preservando filtros. Esse recorte lista os imóveis considerados e o valor usado em cada soma, quando aplicável; o código do imóvel abre `/properties/{id}`.
- A rota `/dashboard/owners` ainda está planejada. As fichas `/owners/{id}` e `/properties/{id}` existem. A ficha do proprietário hoje mostra os imóveis vinculados, mas não explicita os preços que compõem os rankings; a visão de contribuição precisa completar essa rastreabilidade.
- As URLs GET podem ser compartilhadas. Paginação independente por ranking preserva os filtros ativos.

## Estados e consistência visual

- Sem proprietários com imóveis atuais: estado vazio no ranking de quantidade, com acesso à listagem `/owners`.
- Sem preços elegíveis em venda ou aluguel: mensagem própria no painel correspondente. Distinguir ausência de preço de uma soma válida igual a R$ 0,00.
- Filtros sem resultados: indicar qual ranking ficou sem correspondências e oferecer limpeza dos filtros; os outros painéis continuam visíveis.
- Sem registro de contato confirmado: mostrar **—**. Falha de consulta em um painel deve exibir aviso e opção de tentar novamente sem ocultar os demais. Renderização inicial server-side; atualização HTMX indica carregamento apenas na área afetada.
- Herda cartões, filtros, tabelas, contraste, foco e controles do CRM (Pico.css e `app.css`). Em telas estreitas, empilhar os painéis e manter unidade e rótulos junto aos valores; cor não é a única forma de indicar estado.

## Dados e consultas previstas

- Relações: `owners` → `owner_properties` → `properties`; `whatsapp_contact_logs` para o indicador geral.
- Quantidade: agrupar por proprietário, contar imóveis distintos e aplicar modalidade, tipo e situação.
- Venda e aluguel: agregar respectivamente `sale_price` e `rent_price` apenas quando preenchidos e elegíveis pela intenção; aplicar tipo, situação e faixa ao agregado por proprietário. Consultas parametrizadas e executadas no servidor.
- Contribuições: ao selecionar proprietário e ranking, retornar apenas os vínculos atuais que entram naquele cálculo, com a unidade e preço correspondentes. Cada registro de imóvel oferece ligação para sua ficha.
- A implementação permanece em PHP/Slim, SQLite, templates PHP e HTMX. Os rankings detalhados e seus recortes ficam no servidor; as listagens operacionais atuais não oferecem esses agregados.

## Confirmação

As regras numéricas do último contato foram aprovadas na Etapa 0 e estão explícitas acima. Confirme ou corrija este brief para encerrar a Etapa 4.
