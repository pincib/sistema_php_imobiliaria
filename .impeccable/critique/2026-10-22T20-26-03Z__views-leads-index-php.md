---
target: views/leads/index.php
total_score: 27
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\index.php"
target_fingerprint: "sha256:a8da57b57f8f8bda09069cab1f94152e19cdd1ffffcdb871aaa746f2d758f64e"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\index.php"
timestamp: 2026-10-22T20-26-03Z
slug: views-leads-index-php
closed: true
---
Method: dual-agent (A: 27f78da6-539f-4b22-b8ab-51b5a3d79bd1 · B: ac61fa7f-1c91-4bdb-8880-d87825f2ef2b)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Feedback reativo HTMX e loaders adequados; ausência de indicadores de urgência/follow-up na listagem |
| 2 | Match System / Real World | 2 | Faltam elementos vitais da rotina imobiliária na tabela (perfil de busca, próximo retorno); emojis soltos destoam do visual institucional |
| 3 | User Control and Freedom | 3 | Navegação e cancelamento limpos; falta ação rápida para desfazer transição acidental no pipeline |
| 4 | Consistency and Standards | 3 | Pico.css bem aproveitado; redundância entre pílulas de estágio e dropdown na toolbar; estilos inline dispersos |
| 5 | Error Prevention | 3 | Validação de contato, máscara de telefone para WhatsApp, bloqueio de transições ilegais e confirmação para exclusão por admin |
| 6 | Recognition Rather Than Recall | 2 | Chips de sugestão para motivo de perda são exemplares; porém a tabela força o corretor a lembrar o interesse e histórico de cada lead |
| 7 | Flexibility and Efficiency | 2 | Atalho de WhatsApp na ficha acelera contato, mas faltam atalho direto na tabela, atalhos de teclado e ações em lote para gestores |
| 8 | Aesthetic and Minimalist Design | 3 | Estética limpa e utilitária; cabeçalho contém texto descritivo redundante que consome altura útil em laptops |
| 9 | Error Recovery | 3 | Mensagens de validação inline (`.field-error-text`) em vermelho semântico, `aria-invalid` e alertas flash de fácil recuperação |
| 10 | Help and Documentation | 3 | Estados vazios exemplares (`state-box`), diferenciando base limpa de ausência de resultados por busca/filtro com ações claras |
| **Total** | | **27/40** | **Promissor / Operacional Sólido** |

## Design Specificity Verdict

- **Avaliação do Reviewer (LLM)**: A interface atual estabeleceu alicerces operacionais sólidos (máquina de estados estrita, motivo de perda com chips de sugestão e associação N:N de imóveis). Contudo, a listagem de leads ainda se assemelha a uma tabela cadastral genérica: o corretor não vê de imediato o perfil de interesse imobiliário do cliente (bairro, tipologia, faixa de valor) nem o status de agendamento de retorno. A experiência ganha personalidade imobiliária forte na ficha e na máquina de estados, mas precisa trazer essa inteligência para a superfície primária de leitura.
- **Varredura Determinística**: O detector mecânico analisou os 6 arquivos de template e estilos (`index.php`, `table.php`, `show.php`, `create.php`, `edit.php`, `app.css`) em modo regex, com 0 violações de regras estruturais. A análise técnica revelou nuances: presença de `@keyframes spin` e estilos de layout flex inline nas views, botões com hexadecimais fixos (`#22c55e`, `#dc2626`) em vez de tokens CSS adaptáveis ao modo escuro, e botão de reset com `visibility: hidden` sem `tabindex="-1"`.
- **Visual Overlays**: Indisponível neste ambiente de execução local sem navegador com automação ativa.

## Overall Impression
A fundação operacional é madura e à prova de falhas: o fluxo de transição com justificativa obrigatória de perda e a auditoria de atribuições conferem integridade corporativa imediata. A maior oportunidade reside em transformar a tabela de um "repositório de cadastros" em uma ferramenta viva de fechamento de negócios, dando visibilidade imediata a retornos atrasados e perfil de busca do cliente.

## What's Working
1. **Blindagem da Máquina de Estados e Perda Conduzida**: O uso de matriz de transições permitidas aliado à revelação progressiva do motivo de perda com sugestões em um clique reduz drasticamente o atrito sem abrir mão da auditoria.
2. **Auditoria Transparente de Transferência de Carteira**: A exigência de justificativa e a timeline cronológica documentada evitam disputas comerciais na equipe e garantem governança.
3. **Resiliência e Estados da Interface com HTMX**: Busca reativa com debounce, histórico de URLs navegáveis (`hx-push-url="true"`), paginação eficiente no servidor e estados vazios educativos com CTAs claros de recuperação.

## Priority Issues

### [P1] Visibilidade de Follow-up e Temperatura na Tabela Principal
- **Por que importa**: O propósito existencial do produto é garantir que nenhum lead seja esquecido. A ausência de indicador visual de retorno programado na tabela força o corretor a abrir ficha por ficha para saber quem deve ser contatado hoje.
- **Correção Concreta**: Adicionar na tabela a coluna "Próximo Retorno / Status", com badges semânticas de urgência (Vermelho: *Atrasado*, Âmbar: *Hoje*, Neutro: data futura ou *Sem retorno*).
- **Comando sugerido**: `/impeccable layout`

### [P1] Registro Rápido de Interação e Contato na Ficha do Lead (`show.php`)
- **Por que importa**: Viola a meta de "registrar um retorno em menos de 10 segundos". Durante uma chamada telefônica, o corretor não pode ser obrigado a navegar para a tela de edição cadastral para alterar um campo de texto único.
- **Correção Concreta**: Criar na ficha um card operacional de registro rápido de retorno (tipo de contato: WhatsApp/Ligação/Visita, nota objetiva e agendamento do próximo retorno).
- **Comando sugerido**: `/impeccable clarify`

### [P2] Redundância Crítica na Barra de Filtros e Cabeçalho
- **Por que importa**: As pílulas de estágio superiores e o `<select name="stage">` na toolbar competem pela atenção do usuário a 20px de distância. O parágrafo longo no título desperdiça espaço vertical útil em notebooks.
- **Correção Concreta**: Remover o `<select name="stage">` da toolbar mantendo apenas as pílulas horizontais superiores; enxugar o texto descritivo do cabeçalho; adicionar botão de WhatsApp direto na coluna de contato da tabela.
- **Comando sugerido**: `/impeccable distill`

### [P3] Contexto Imobiliário Oculto na Listagem
- **Por que importa**: Corretores operam por imóveis e bairros. Uma tabela apenas com nomes de pessoas causa lentidão no atendimento diário.
- **Correção Concreta**: Exibir sob o nome do cliente uma linha compacta com o resumo de interesse (ex: `📍 Icaraí • Apto 2Q • até R$ 500k` ou código do imóvel vinculado `#AP101`).
- **Comando sugerido**: `/impeccable polish`

### [P3] Padronização de Ícones e Tokens de Cor
- **Por que importa**: Emojis de sistema soltos (`📞`, `💬`, `🗑️`) degradam a percepção de produto profissional no Windows e cores hexadecimais fixas ignoram a alternância limpa para modo escuro.
- **Correção Concreta**: Substituir emojis por SVGs lineares inline padronizados e mapear cores de alerta/sucesso para tokens CSS semânticos.
- **Comando sugerido**: `/impeccable polish`

## Persona Red Flags

- **Marcos (Corretor de Alto Volume — Laptop 13", rotina acelerada)**:
  - *Red Flag*: Não consegue fazer a triagem matinal de contatos na tabela porque não sabe quem está com retorno atrasado.
  - *Red Flag*: Precisa abrir a ficha completa do lead apenas para disparar uma mensagem de WhatsApp, em vez de disparar com 1 clique diretamente da tabela.
  - *Red Flag*: Não tem como registrar rapidamente o resumo de uma conversa em menos de 10 segundos na ficha.

- **Gestor Imobiliário (Admin — Governança e Distribuição da Carteira)**:
  - *Red Flag*: Não possui visibilidade de leads estagnados no pipeline (parados há semanas no mesmo estágio).
  - *Red Flag*: Falta de atribuição em lote para novos leads recebidos via site/portais.

## Minor Observations
- O botão "Limpar Filtros" usa `visibility: hidden;` para preservar a coluna do grid, o que pode manter o elemento focável via teclado se não receber `tabindex="-1"`.
- As tags `<details>` para vincular imóvel e transferir responsável em `show.php` quebram a escaneabilidade imediata; formulários em cards diretos ou botões modais seriam mais rápidos.

## Questions to Consider
1. Se 80% do primeiro contato é via WhatsApp, clicar no WhatsApp na tabela não deveria abrir a conversa e já sugerir o agendamento de retorno?
2. Em um CRM local-first de alta produtividade, atalhos de teclado (ex: `/` para buscar, `N` para novo lead, `J`/`K` para navegar linhas) não dobrariam a agilidade do corretor?
