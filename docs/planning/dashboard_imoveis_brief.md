# Brief — dashboard detalhada de Imóveis

**Etapa:** 2 — Planejamento da dashboard de Imóveis  
**Status:** proposta consolidada para confirmação  
**Superfície planejada:** `/dashboard/properties`  
**Modo:** Operate

## Trabalho e público

Corretores e administradores precisam entender quais imóveis atraem mais leads e quais tipos concentram interesse. A dashboard deve ajudar a comparar rapidamente esses registros e chegar ao imóvel e aos leads que compõem cada contagem.

## Conteúdo e hierarquia

1. Cabeçalho **Análise de Imóveis**, com caminho de retorno ao resumo principal e indicação do período aplicado.
2. Barra de filtros antes dos resultados: data inicial/final, tipo de imóvel, situação do imóvel e estágio atual do lead.
3. O ranking de imóveis é a análise principal: tabela ordenada pela quantidade de leads distintos, com até 15 imóveis por página. Colunas: código/nome, tipo, situação, quantidade de leads e acesso ao imóvel.
4. A comparação por tipo fica como análise secundária, em tabela compacta ordenada pela quantidade de leads distintos. Mostrar contagens, sem percentuais: um lead pode estar ligado a imóveis de tipos diferentes, então os grupos se sobrepõem.
5. Por padrão, período, tipo, situação e estágio não restringem os resultados. A visão cobre todo o histórico e todos os status e estágios.

## Definição e combinação dos filtros

- Contar leads distintos em vínculos atuais (`lead_properties`), em qualquer `interest_type`; vínculos removidos não fazem parte da métrica.
- `COUNT(DISTINCT l.id)` evita contar duas vezes o mesmo lead para o mesmo imóvel e evita duplicação na comparação por tipo. A relação atual tem restrição única por `(lead_id, property_id)`.
- O período usa `leads.created_at`, conforme a Etapa 0, e seus limites são interpretados no fuso configurado para o CRM. Imóvel, tipo, situação e estágio filtram os registros em conjunto por interseção (`AND`).
- O filtro de estágio consulta o estágio atual do lead, usando os valores reais de `PipelineManager::STAGES`. `ganho` e `perdido` continuam incluídos até que sejam explicitamente filtrados.
- Tipos e situações vêm dos catálogos de `PropertyService`. A distribuição usa os rótulos canônicos. Dados legados fora do catálogo devem aparecer agrupados como **Tipo não identificado**, para não desaparecerem silenciosamente; as importações atuais normalizam tipos conhecidos e convertem desconhecidos para `comercial`.
- Não há filtro por corretor. Todos os usuários autenticados compartilham os mesmos resultados, respeitando o acesso já usado nos módulos.

## Navegação e rastreabilidade

- A distribuição por tipo pode ser selecionada para aplicar esse tipo aos resultados e ao ranking de imóveis, preservando os outros filtros.
- O código/nome de um imóvel abre sua ficha existente em `/properties/{id}`. A ficha mostra os leads relacionados e oferece links às fichas individuais em `/leads/{id}`.
- A quantidade de leads oferece um recorte da dashboard de Imóveis para aquele imóvel, preservando período, situação e estágio selecionados; a tabela de leads do recorte respeita esses filtros. O destino da análise é `/dashboard/properties?property_id={id}`. Se também precisar consultar todos os vínculos atuais, a ficha existente continua disponível pelo código/nome.
- A distribuição por tipo seleciona esse tipo na própria dashboard e preserva os demais filtros. O ranking de Leads detalhado da Etapa 3 pode receber o estágio inicial quando a navegação partir de outro resumo.
- Os filtros são enviados por GET, podem ser compartilhados por URL e permanecem ao paginar. Ao mudar filtros, voltar à primeira página.
- A rota `/dashboard/properties` é planejada, ainda não existe. `/properties/{id}` e `/leads/{id}` já existem.

## Estados e interação

- Sem vínculos em toda a base: mostrar que ainda não há leads relacionados a imóveis, com acesso à listagem de Imóveis para iniciar a consulta/cadastro.
- Filtros sem resultado: informar que nenhum imóvel corresponde à combinação e oferecer limpeza dos filtros.
- Sem tipos com leads no recorte: mostrar estado vazio específico na comparação por tipo. Quando houver apenas tipos legados desconhecidos, manter a contagem em **Tipo não identificado**.
- Se filtros recebidos forem inválidos, ignorar o valor não reconhecido, explicar qual filtro foi desconsiderado e manter a consulta segura; valores de filtro sempre são validados e vinculados como parâmetros.
- Falha de consulta: apresentar aviso junto ao painel afetado e permitir nova tentativa, mantendo outros resultados visíveis. Na atualização HTMX de filtros, indicar carregamento na área atualizada; renderização inicial é server-side.
- Valores monetários não são métricas desta superfície. Dados pessoais dos leads devem aparecer somente quando necessários para rastrear os registros e respeitando o que já é exposto nas fichas.

## Dados e consultas previstas

- Fonte principal: `properties` → `lead_properties` → `leads`.
- Consulta do ranking: agregar por imóvel e contar leads distintos; aplicar filtros de `type`, `status`, `stage` e faixa de `leads.created_at`; ordenar por contagem descendente e, em empate, por código do imóvel.
- Consulta por tipo: agrupar pelo tipo persistido e contar leads distintos sob os mesmos filtros; ordenar por contagem descendente e rótulo.
- Lista rastreável: no recorte por imóvel, selecionar os leads vinculados e aplicar os mesmos filtros temporais e de estágio ativos. Para dados não agregados sem filtros, a ficha existente já usa `PropertyRepository::relatedLeads`; o recorte filtrado requer consulta parametrizada e paginada.
- O ranking retorna apenas imóveis com ao menos um lead correspondente ao recorte. Tabelas e paginação ficam no servidor; limite de 15 por página.

## Layout e limites

- Herdar a linguagem operacional existente: Pico.css, cartões/tabelas, foco visível, contraste semântico e controles familiares.
- Em telas largas, filtros em uma faixa compacta e ranking/tipo em colunas lado a lado; em telas estreitas, filtros empilhados e análises em sequência. A tabela deve se adaptar ao padrão responsivo de registros existente, mantendo rótulos de coluna junto a cada valor.
- Usar números para comparação exata; barras decorativas e percentuais não são necessários. Cor não deve ser a única forma de comunicar estágio ou situação.
- A implementação permanece em PHP/Slim, SQLite, templates PHP e HTMX; agregações são SQL parametrizado e executado no servidor. Os limites de data precisam respeitar `APP_TIMEZONE` e a convenção de timestamps documentada na Etapa 0.

## Confirmação

O ranking, filtros e comparação por tipo seguem as decisões da Etapa 0. A ação sobre a quantidade de leads deve levar à análise filtrada por imóvel; a ficha existente do imóvel continua disponível pelo código/nome. Confirme ou corrija esse brief antes de encerrar a Etapa 2.
