# Brief — resumo gerencial da dashboard principal

**Etapa:** 1 — Planejamento da dashboard principal  
**Status:** proposta consolidada para confirmação  
**Superfície:** `views/dashboard/index.php`  
**Modo:** Operate

## Trabalho e público

Corretores e administradores consultam a página no começo e durante o expediente para organizar contatos. A agenda de follow-ups continua sendo a tarefa principal; o resumo gerencial permite perceber rapidamente a situação de Leads, Imóveis e Proprietários e abrir a análise detalhada quando necessário.

## Hierarquia e conteúdo

1. Preservar o cabeçalho atual, a saudação e o atalho **+ Novo Lead**.
2. Manter a **Agenda Operacional de Follow-ups** como primeiro e principal bloco da página, com filtros e ações atuais.
3. Logo após a agenda, incluir **Resumo gerencial**, nesta ordem: **Leads**, **Imóveis**, **Proprietários**.
4. No bloco de Leads, mostrar três contagens compactas: Proposta, Fechados e Perdidos. Sem gráfico ou tabela.
5. No bloco de Imóveis, mostrar até três imóveis com mais leads e até três tipos com mais leads.
6. No bloco de Proprietários, mostrar até três em cada ranking de quantidade de imóveis, valor de venda e valor de aluguel. Exibir também o número de dias desde o contato confirmado mais recente; não mostrar nome junto desse número.
7. Os rankings do resumo terão **até três itens** cada. A janela é histórica por padrão e não recebe os filtros da agenda de follow-ups.
8. Manter os cartões de navegação para os módulos e os indicadores de administração existentes abaixo do resumo, conforme suas permissões atuais.

## Navegação

- Cada bloco terá uma ação **Ver dashboard** para a análise detalhada da área. Destinos planejados: `/dashboard/leads`, `/dashboard/properties` e `/dashboard/owners`; essas rotas serão entregues nas etapas das respectivas áreas.
- Cada contagem de estágio abre a análise de Leads com o estágio correspondente selecionado (`proposta`, `ganho` ou `perdido`).
- Cada item de ranking abre a análise detalhada da área com o imóvel, tipo ou proprietário selecionado. A análise permite chegar aos registros de origem.
- Os cartões de módulo existentes continuam levando às listagens operacionais (`/leads`, `/properties`, `/owners` e `/signboards`). A ação **+ Novo Lead** continua disponível no cabeçalho.

## Layout e responsividade

- Reutilizar a composição de seções, cartões e grade do produto, com o vocabulário visual atual do Pico.css e `app.css`.
- Em telas largas, dispor Leads e Imóveis lado a lado; Proprietários ocupa a linha seguinte, com seus três rankings organizados para comparação rápida.
- Em telas estreitas, empilhar os blocos na ordem Leads → Imóveis → Proprietários. Manter cada valor associado ao seu rótulo e permitir que nomes e valores longos quebrem sem sobreposição.
- Preservar títulos semânticos, foco visível, links e botões operáveis por teclado. Não depender somente de cor para identificar estágios ou situações.

## Estados e interação

- A página e os indicadores são renderizados no servidor. No acesso inicial, não mostrar carregamento separado para os indicadores; manter o feedback HTMX que já existe para a agenda.
- Contagens sem ocorrências exibem `0`. Um ranking sem itens apresenta uma mensagem curta de ausência e mantém o link para a análise detalhada.
- Se não houver registro confirmado de contato com proprietário, mostrar `—` para o indicador de dias.
- Falha de consulta em um indicador afeta somente aquele bloco. Mostrar aviso local e ação para tentar novamente; manter visíveis a agenda e os demais indicadores. A tentativa pode atualizar apenas o bloco por HTMX e mostrar seu estado de carregamento enquanto consulta.
- Dados opcionais ausentes seguem as regras da Etapa 0: valores de preço nulos não entram nos rankings de valor; estado vazio ou parcial deve ser explicado sem ocultar os outros resultados.

## Limites e decisões já estabelecidas

- Acesso compartilhado para corretores e administradores, sem filtro por corretor.
- Leads contam pelo estágio atual; filtros das páginas detalhadas usam a criação do lead. Imóveis contam vínculos atuais a leads distintos, em qualquer estágio.
- Rankings de Proprietários usam vínculos atuais, somas separadas de venda e aluguel e todos os status de imóvel.
- A contagem de dias do contato usa o registro confirmado mais recente disponível no sistema. Como a tabela não possui uma data própria do contato realizado, usa o instante de registro do resultado, conforme o brief de métricas.
- Implementação futura permanece em PHP/Slim, SQLite, templates PHP e HTMX. Este brief planeja a experiência; não cria endpoints, consultas ou telas.

## Evidência consultada

- `views/dashboard/index.php`
- `views/dashboard/partials/follow_ups_container.php`
- `src/Modules/Dashboard/Controllers/DashboardController.php`
- `src/Application.php`
- `views/layouts/main.php`
- `public/assets/css/app.css`
- `docs/planning/dashboard_metricas_decisoes.md`

## Confirmação

Os destinos detalhados acima são rotas planejadas, ainda não existentes. A hierarquia, densidade, comportamento dos links e estados aguardam confirmação antes de encerrar a Etapa 1.
