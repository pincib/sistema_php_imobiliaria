---
target: views/dashboard/index.php
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\dashboard\\index.php"
target_fingerprint: "sha256:1d97c94d93873d2809a62c0a74993faefb7f1724ad1ddb2ec44272d5a1173a4e"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\dashboard\\index.php"
timestamp: 2026-10-23T12-56-58Z
slug: views-dashboard-index-php
closed: true
---
Method: dual-agent (A: bb917f0f-b67e-4f71-bdf2-986835649fe6 · B: fc2410c0-665c-43c7-9846-8a96ab2ec757)

### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|:-----:|-----------|
| 1 | Visibility of System Status | 3 | HTMX lida bem com filtros, mas modal de conclusão submete via POST tradicional sem feedback otimista. |
| 2 | Match System / Real World | 3 | Linguagem imobiliária e botões diretos de WhatsApp/Ligação são excelentes, mas telemetria DevOps polui a tela. |
| 3 | User Control and Freedom | 2 | Ausência de ação de desfazer (Undo) em conclusões acidentais; falta fechamento de modal por clique externo. |
| 4 | Consistency and Standards | 2 | Contradição espacial: cards ordenam [Atrasados, Hoje, Futuro], enquanto abas logo abaixo invertem a ordem. |
| 5 | Error Prevention | 3 | Proteção idempotente e anti-duplo clique sólidas, mas inputs datetime-local não possuem atalhos rápidos (+2h, Amanhã). |
| 6 | Recognition Rather Than Recall | 3 | Informações cadastrais visíveis, porém falta contexto do imóvel de interesse (código, valor) nos cards de retorno. |
| 7 | Flexibility and Efficiency | 2 | Sem navegação por teclado (J/K, C para concluir, R para reagendar); múltiplos cliques com mouse para rotinas diárias. |
| 8 | Aesthetic and Minimalist Design | 2 | Poluição visual com navegação duplicada, métricas de infraestrutura e sobreposição de 4px de bordas coloridas unilaterais. |
| 9 | Error Recovery | 3 | Validação HTML5 nativa; falhas de validação no servidor em modal fecham o diálogo forçando redigitação. |
| 10 | Help and Documentation | 2 | Textos descritivos claros nos cabeçalhos, mas zero tooltips sobre regras de atraso ou colaboração da carteira. |
| **Total** | | **25/40** | **Acceptable** |

### Design Specificity Verdict

**LLM assessment**: A estrutura da agenda operacional baseada na urgência temporal (Atrasados, Hoje, Futuros, Concluídos) com atalhos de discagem e WhatsApp possui autenticidade no mercado imobiliário brasileiro. Contudo, a identidade é diluída por caixas de telemetria de infraestrutura de servidor (*Sessões Concorrentes*, *Eventos Auditados*) típicas de templates genéricos como AdminLTE/Nova, e pela ausência de metadados imobiliários nos cards (código do imóvel, bairro ou orçamento de interesse do lead).

**Deterministic scan**: O motor CLI detectou 1 violação direta `side-tab` (borda unilateral espessa colorida `#16a34a` na linha 383 de `follow_ups_container.php`) e identificou que o mesmo padrão visual de IA (`border-left: 4px solid ...`) está replicado sistematicamente em 8 regras de `.stat-card-*` e `.follow-up-card.is-*` em `app.css`.

**Visual overlays**: Overlay no navegador ignorado no contexto CLI/subagente devido à exigência de sessão autenticada do Slim e ausência de sessão browser interativa.

### Overall Impression
A agenda cumpre com firmeza o objetivo de não esquecer retornos, mas sofre de sobrecarga cognitiva pelo excesso de controles empilhados (cards seguidos de abas invertidas e filtros), fricção no registro de ligações não atendidas e poluição por métricas que interessam apenas ao desenvolvedor/administrador de infraestrutura.

### What's Working
1. **Triagem Imediata por Urgência Temporal:** Segregar visualmente a carteira em Atrasados, Hoje, Futuros e Concluídos com indicadores claros dá clareza imediata ao início da jornada de trabalho.
2. **Micro-Ações Diretas de Comunicação (WhatsApp e Telefone):** Atalhos `wa.me/55...` e `tel:...` evitam cópia e cola manual, alinhando-se exatamente ao dia a dia do corretor.
3. **Reforço Positivo na Fila Zerada (Peak-End Rule):** A mensagem encorajadora quando não há atrasados gera alívio e satisfação operacional genuínos.

### Priority Issues

- **[P1] Navegação Duplicada Redundante e Ordem Espacial Invertida**
  - **Why it matters:** 4 cards de situação seguidos de 5 botões de abas com as mesmas informações consomem espaço nobre e forçam reorientação mental pela inversão da ordem `[Atrasados -> Hoje]` vs `[Hoje -> Atrasados]`.
  - **Fix:** Unificar os stat-cards e as abas em uma única barra superior de seleção direta, onde o próprio card selecionado atua como filtro ativo com anel de foco.
  - **Suggested command:** `/impeccable layout`

- **[P1] Falta de Contexto Imobiliário nos Cards de Follow-up**
  - **Why it matters:** O corretor vê o nome e telefone, mas não sabe qual imóvel o cliente visitou ou procura, sendo forçado a abrir a ficha completa do lead em outra aba para cada ligação.
  - **Fix:** Exibir um chip compacto com o código do imóvel ou faixa de interesse (ex: `🏠 AP101 - Icaraí`) diretamente no card.
  - **Suggested command:** `/impeccable clarify`

- **[P2] Alta Fricção para Contatos Rotineiros ("Não Atendeu")**
  - **Why it matters:** Em 40% a 50% das tentativas de contato, o cliente não atende. O corretor precisa abrir um modal, digitar justificativa, expandir campos de data e submeter via POST completo.
  - **Fix:** Incluir botões rápidos de 1 clique nos cards: `📞 Não Atendeu (+2h)` e `🕒 Ligar Amanhã 10h` para registro instantâneo sem abrir modal.
  - **Suggested command:** `/impeccable polish`

- **[P2] Foco Comercial Diluído por Telemetria de Infraestrutura**
  - **Why it matters:** *Usuários Ativos*, *Sessões Concorrentes* e *Eventos Auditados* não ajudam a vender imóveis e ocupam espaço que deveria ser de leads novos e propostas abertas.
  - **Fix:** Remover métricas de infraestrutura da visão operacional do corretor e substituí-las por indicadores de vendas (*Leads sem contato >24h*, *Propostas ativas*).
  - **Suggested command:** `/impeccable distill`

- **[P3] Bordas Unilaterais Artificiais (AI Slop `side-tab`) e Contraste no Modo Escuro**
  - **Why it matters:** Bordas esquerdas de 4px coloridas são identificadas como padrão de IA e cores hardcoded em estilo inline (`#4f46e5`) falham no contraste WCAG AA no tema escuro.
  - **Fix:** Substituir bordas unilaterais por chips sutis de status e utilizar variáveis CSS de tema calibradas para contraste AA.
  - **Suggested command:** `/impeccable colorize`

### Persona Red Flags

- **Marcos (Corretor multitarefa/apressado):** Precisa ligar entre visitas pelo notebook no carro; perde tempo abrindo ficha em nova aba para saber o imóvel de interesse e desiste de registrar contatos após ter que preencher modais complexos para chamadas não atendidas.
- **Luciana (Gestora Comercial):** Encontra métricas de infraestrutura de servidor em vez de relatórios sobre quais corretores possuem mais pendências atrasadas no dia.
- **Alex (Power User / Teclado):** Enfrenta 60+ diálogos modais duplicados no DOM, sem atalhos de teclado (J/K para navegação de cards, C para concluir, R para reagendar).

### Minor Observations
- **Duplicação de Modais no DOM:** Cada item do loop renderiza 2 diálogos `<dialog>` completos. Com 30 itens, são 60 formulários ocultos no DOM. Um modal dinâmico hidratado via JS/HTMX seria muito mais leve.
- **Scroll Interno em Cards:** Descrições longas possuem `max-height: 4.5rem; overflow-y: auto;`, gerando barras de rolagem aninhadas desagradáveis.
- **Desconexão com o Pipeline:** Concluir um contato de qualificação não permite avançar o estágio do lead diretamente na Dashboard.

### Questions to Consider
- O que aconteceria se a Dashboard tivesse um "Modo Discador Rápido", permitindo ao corretor liquidar 10 retornos matinais em menos de 3 minutos com atalhos de 1 clique?
- Como a inclusão do código do imóvel de interesse no card transformaria a velocidade das ligações?
- Por que manter métricas de sessões de servidor na tela principal de uma equipe comercial?
