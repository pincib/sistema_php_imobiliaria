# Brief — definições das métricas das dashboards

**Etapa:** 0 — Levantamento e decisões de produto  
**Status:** decisões consolidadas para confirmação  
**Data:** 24/10/2026

## Objetivo e público

Dar a corretores e administradores uma leitura gerencial rápida da operação, com métricas que possam ser conferidas nos registros de Leads, Imóveis e Proprietários. A dashboard principal continua priorizando os follow-ups. Os indicadores gerenciais usam dados compartilhados pela equipe, sem filtro por corretor.

## Regras aprovadas

### Leads e pipeline

- Os rótulos correspondem aos estágios existentes: **Proposta** = `proposta`; **Fechados** = `ganho` (“Ganho / Fechado”); **Perdidos** = `perdido`.
- `negociacao` permanece um estágio próprio; não é contado como proposta nem como fechamento. Não há estado paralelo para descarte: perdas exigem o motivo previsto pelo pipeline.
- As contagens mostram o estágio atual. Leads reabertos ou retomados passam a contar somente no estágio em que estão agora.
- O período padrão é **todo o histórico**. Quando houver filtro de período para Leads, a data de referência será `leads.created_at`; o estágio contado continua sendo o atual, inclusive para leads criados no intervalo cuja etapa mudou depois.

### Imóveis relacionados a Leads

- Imóveis com mais leads e tipos com mais leads contam **leads distintos nos vínculos atuais** (`lead_properties`), independentemente do estágio do lead ou do tipo da relação.
- Vínculos removidos não entram no cálculo; o registro de auditoria da remoção não transforma a métrica em histórico de vínculos. A restrição única `(lead_id, property_id)` já impede duplicidade desse par.
- O filtro de período usa a data de criação do lead associado (`leads.created_at`), igual à dashboard de Leads. Tipo de imóvel e situação do imóvel podem ser usados como filtros próprios, sem alterar a definição padrão.
- A distribuição por tipo usa o catálogo canônico de `PropertyService::TYPES` e os valores gravados em `properties.type`. A importação normaliza variantes conhecidas; valores desconhecidos são convertidos para `comercial` pelo importador atual.

### Proprietários e imóveis vinculados

- O ranking por quantidade conta imóveis distintos atualmente vinculados ao proprietário (`owner_properties`), incluindo todos os status. Vínculos removidos não contam.
- Os rankings de valor são separados por venda e aluguel e ordenados pela **soma** dos valores cadastrados nos imóveis atualmente vinculados. O status não exclui um imóvel.
- `sale_price` alimenta venda e `rent_price` alimenta aluguel conforme a intenção do imóvel. Imóveis com intenção `venda_locacao` podem entrar nos dois rankings. Valores nulos são ignorados; valor zero é um valor cadastrado e permanece válido.
- Os valores são apresentados em BRL. Filtros detalhados podem restringir por modalidade, tipo de imóvel, faixa de valor e situação; sem filtro, valem as regras acima.

### Último contato de proprietários

- O resumo mostra **dias corridos desde o registro confirmado mais recente** de contato com qualquer proprietário, sem exibir nome.
- A fonte é `whatsapp_contact_logs`, com `contact_type = 'owner'` e resultado `confirmado pelo corretor`. Resultados “não enviado” e “cancelado” não contam.
- O esquema registra `created_at`, mas não uma data/hora separada do contato realizado. Portanto, a métrica mede o tempo desde o registro do resultado no sistema, como aproximação do contato. Se ainda não houver registro confirmado, o valor fica `—`.

## Filtros, acesso e apresentação

- Corretores e administradores veem os mesmos dados e rankings. Não haverá filtro por corretor nesta entrega.
- Os filtros de Leads usam período de criação, estágio e demais filtros gerais já suportados pelas listagens. Imóveis podem filtrar por período de criação do lead associado, tipo, situação e estágio do lead. Proprietários podem filtrar por modalidade, tipo, faixa de valor e situação quando aplicável.
- Rankings resumidos usam **até cinco itens** por bloco; rankings detalhados podem paginar ou limitar resultados.
- Exibir contagens com unidade, valores monetários em BRL e período ativo quando houver filtro. Os detalhes devem permitir conferir os registros que compõem cada número.
- Estados sem registros e filtros sem resultados devem informar claramente a ausência de dados. Quando não houver contato confirmado, usar `—`, sem nome de proprietário.

## Contexto técnico e pontos de atenção

- Público: equipe comercial e administradores do CRM local-first. Modo da superfície: **Operate**. A implementação posterior deve seguir PHP/Slim, SQLite, templates PHP e HTMX, mantendo a agenda operacional e renderização no servidor.
- Datas de período devem respeitar o fuso configurado em `APP_TIMEZONE` (padrão `America/Sao_Paulo`). Antes de implementar os filtros, conferir a convenção dos timestamps: SQLite `CURRENT_TIMESTAMP` gera UTC, enquanto alguns valores são construídos por `DateHelper` no fuso da aplicação. Converter limites de dia de forma consistente com os valores persistidos.
- A auditoria registra transições de etapa como eventos JSON, mas não existe tabela estruturada de histórico do pipeline. Por isso as métricas aprovadas são retratos do estágio atual, não análises de transições ou conversão por período.
- Placas ficam fora desta definição de métricas; a Etapa 5 decidirá se terão dashboard gerencial.

## Evidência consultada

- `src/Modules/Leads/Pipeline/PipelineManager.php`
- `src/Modules/Leads/Services/LeadService.php`
- `database/migrations/004_create_leads_properties_pipeline.sql`
- `database/migrations/011_create_owners_and_whatsapp_logs.sql`
- `database/migrations/013_expand_property_business_fields.sql`
- `src/Modules/Properties/Services/PropertyService.php`
- `src/Modules/Leads/Services/MultiDatasetImportService.php`
- `src/Modules/Reports/Services/ReportsService.php`
- `src/Support/DateHelper.php`

## Questões em aberto

- Nenhuma decisão de produto bloqueia o início da Etapa 1. A ressalva sobre `created_at` do contato confirmado e a convenção de fuso deve acompanhar o trabalho técnico das Etapas 6 e 9.
