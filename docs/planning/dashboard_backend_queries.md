# Contratos de dados — dashboards

**Etapa:** 6 — Preparação de dados e implementação backend  
**Status:** conectado às superfícies da Etapa 7

## Acesso e integração

- As consultas de domínio ficam em `src/Modules/Dashboard/Repositories/`: Leads, Imóveis e Proprietários têm repositórios separados e só leitura.
- `DashboardDataService` valida parâmetros de URL, converte datas, aplica limites e entrega modelos de dados à camada de apresentação.
- A rota autenticada existente `/` agora fornece `managementSummary` para o resumo; a agenda continua sendo consultada e renderizada como antes. Falha no resumo é capturada sem interromper a agenda.
- As métricas do resumo são consultadas isoladamente e informam falha por chave em `managementSummaryErrors`, para que a Etapa 7 mantenha os blocos disponíveis sem dados independentes dos que falharam.
- As rotas detalhadas `/dashboard/leads`, `/dashboard/properties` e `/dashboard/owners` estão conectadas ao serviço e protegidas pelo mesmo grupo autenticado dos módulos de origem.
- Não há filtro por corretor. O acesso permanece no grupo autenticado de corretores e administradores, como os módulos de origem.

## Regras de consulta

### Resumo principal

- Leads: conta o estágio atual em `leads.stage` e retorna as três contagens aprovadas: `proposta`, `ganho` e `perdido`; todo o histórico por padrão.
- Imóveis: conta `COUNT(DISTINCT leads.id)` por vínculo atual em `lead_properties`, independentemente de estágio e `interest_type`. Retorna até três imóveis, ordenados por contagem decrescente e código.
- Tipos: conta leads distintos por tipo sob os mesmos vínculos atuais; categorias podem se sobrepor. Tipos fora do catálogo são reunidos como `unknown` (“Tipo não identificado”). O resumo retorna até três tipos.
- Proprietários: quantidade conta imóveis distintos em `owner_properties`; venda soma `sale_price` com intenção `venda` ou `venda_locacao`; aluguel soma `rent_price` com intenção `locacao` ou `venda_locacao`. Todos os status entram, nulos são ignorados e zero permanece elegível. Cada ranking retorna até três proprietários.
- Último contato: busca o `created_at` mais recente em `whatsapp_contact_logs` para contato de proprietário com resultado confirmado. O serviço calcula dias corridos no fuso da aplicação e retorna `null` quando não existe registro.

### Dashboard de Leads

- `stageCounts()` agrupa por estágio atual e período opcional de `leads.created_at`; não interpreta auditoria como histórico de transições.
- `paginateLeads()` filtra pela mesma data e pelo estágio selecionado, lista até 15 por página, em ordem de criação mais recente e ID como desempate. Retorna somente ID, nome, estágio, responsável e data; e-mail e telefones ficam fora.

### Dashboard de Imóveis

- `paginateProperties()` e `leadCountsByType()` aplicam em conjunto data de criação do lead, estágio atual, tipo e situação. Todo filtro é opcional.
- As contagens usam leads distintos em associações atuais. O ranking exclui imóveis sem leads no recorte e pagina 15 por vez; empate ordena pelo código.
- `paginatePropertyLeads()` lista até 15 leads de um imóvel no mesmo recorte, sem expor informações de contato. A consulta usa associação e datas/estágio selecionados.
- A comparação de tipos não usa percentuais. Um lead pode entrar em mais de um tipo por estar associado a imóveis diferentes.

### Dashboard de Proprietários

- Cada ranking agrega somente vínculos atuais e usa os filtros de tipo/situação.
- O ranking de quantidade pode filtrar modalidade e conta imóveis distintos. Venda e aluguel usam suas intenções e preços próprios. O filtro de faixa monetária é aplicado ao total agregado do proprietário (`HAVING`), não ao preço unitário do imóvel.
- Cada ranking tem paginação independente de 15 itens. Empates são ordenados pelo nome.
- `paginateContributions()` retorna os imóveis que formam a soma, com preço/unidade de contribuição, e valida que o proprietário pertence ao ranking filtrado antes de listar. Não retorna telefone, e-mail nem notas do proprietário.

## Parâmetros, datas e cardinalidade

- Valores de filtros, datas, estágios, modalidade, limite e deslocamento são associados a parâmetros PDO. Os trechos SQL condicionais são escolhidos apenas por validação/código interno; entrada do usuário nunca é concatenada como valor.
- Datas de URL usam `YYYY-MM-DD`, com início e fim inclusivos no calendário local. Os limites são convertidos para UTC e consultados como intervalo semiaberto (`>= início`, `< dia seguinte`). Isso corresponde aos `created_at` gerados por `CURRENT_TIMESTAMP`, que o SQLite persiste em UTC.
- Estágios, tipos e situações são validados pelos catálogos do sistema. Parâmetros inválidos são ignorados e devolvidos em `errors`; datas invertidas descartam o intervalo inteiro. Valores monetários negativos ou inválidos são ignorados; uma faixa mínima maior que a máxima é descartada.
- As relações `lead_properties` e `owner_properties` têm unicidade por par, e as métricas ainda usam `COUNT(DISTINCT ...)` onde apropriado. Cada domínio agrega antes de qualquer lista de contribuições, evitando multiplicação por junções.

## Índices e limites

- Reutilizados os índices existentes: `idx_leads_created_at`, `idx_leads_stage`, `idx_lead_properties_property`, `idx_lead_properties_lead`, `idx_owner_properties_owner`, `idx_owner_properties_property`, e índices de tipo/situação/intenção de imóveis.
- Nenhum índice novo foi adicionado: o volume previsto é uma equipe pequena/média local e os caminhos principais já têm índice nas chaves de ligação e nas datas filtradas. Reavaliar com `EXPLAIN QUERY PLAN` se o volume real mostrar consultas lentas.
- Limites de leitura: até 3 por ranking do resumo e 15 por página em cada lista detalhada ou recorte de contribuições.

## Interfaces de código

- `DashboardDataService::principalSummary()` — resumo server-side da página principal.
- `DashboardDataService::leadsDashboard(array $query)` — métricas e lista paginada de leads.
- `DashboardDataService::propertiesDashboard(array $query)` — ranking, distribuição por tipo e lista filtrada de leads de um imóvel.
- `DashboardDataService::ownersDashboard(array $query)` — rankings independentes, contribuições selecionadas e indicador geral de contato.

Esses métodos fornecem os dados para as páginas da Etapa 7. As rotas detalhadas e os estados visuais estão conectados ao resumo e às páginas de Leads, Imóveis e Proprietários; a área de Placas permanece na tela operacional existente, conforme decisão da Etapa 5.
