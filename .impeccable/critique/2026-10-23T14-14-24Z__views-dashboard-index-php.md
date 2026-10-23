---
target: views/dashboard/index.php
total_score: 26
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\dashboard\\index.php"
target_fingerprint: "sha256:c0e4b161578111a58b03d7c4e9f8dfe68394f5d00697cf83750944feba8c03a2"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\dashboard\\index.php"
timestamp: 2026-10-23T14-14-24Z
slug: views-dashboard-index-php
closed: true
---
Method: dual-agent (A: 65d226e1-09ac-4a45-9821-aed0058ce3f7 · B: ffd26e78-85bc-4eec-9c04-b128bff56d12)

### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|---|:---:|---|
| 1 | Visibility of System Status | 3 | Feedback reativo com HTMX e contador regressivo precisos; perda de estado devido ao redirect 302 pós-modal. |
| 2 | Match Between System and Real World | 3 | Vocabulário comercial imobiliário autêntico e atalhos rápidos (`Não Atendeu +2h`), mas faltam âncoras financeiras (R$ e tipo de negócio). |
| 3 | User Control and Freedom | 2 | Ausência de desfazer (Undo) para o atalho de 1 clique "Não Atendeu (+2h)"; sem ações em lote. |
| 4 | Consistency and Standards | 2 | Conflito de paradigmas: Stat Cards e Pill Buttons duplicam a mesma filtragem lado a lado; formulário modal usa POST síncrono enquanto o resto usa HTMX. |
| 5 | Error Prevention | 3 | Proteção robusta contra duplo clique e idempotência, mas `datetime-local` permite agendar datas no passado sem bloqueio visual. |
| 6 | Recognition Rather Than Recall | 3 | Cards expõem contexto completo do lead, mas o modal de resultado encobre os detalhes do histórico anterior. |
| 7 | Flexibility and Efficiency of Use | 3 | Atalhos de alta velocidade para chamadas e WhatsApp; sem atalhos de teclado nem triagem em lote de pendências atrasadas. |
| 8 | Aesthetic and Minimalist Design | 2 | Sobrecarga visual: três camadas redundantes de contadores/cards e blocos informativos genéricos de módulos no rodapé. |
| 9 | Help Users Recognize, Diagnose, and Recover from Errors | 3 | Banners resilientes para quedas de conexão local, mas classe `.alert-danger` quebrou o estilo visual do alerta de erro. |
| 10 | Help and Documentation | 2 | Subtítulos orientadores presentes, mas faltam tooltips nas etapas do funil e estados de onboarding para primeiro uso. |
| **Total** | | **26/40** | **Operate Necessita Refinamento (Nível Médio)** |

---

### Design Specificity Verdict

**LLM Assessment (Direção de Design):**
A interface apresenta autenticidade operacional evidente nas micro-interações do corretor: o atalho de 1 clique "📞 Não Atendeu (+2h)", links diretos para `wa.me` e `tel:`, e o vínculo visual com o imóvel alvo. No entanto, o design ainda é 35% intercambiável por falhar em expor o núcleo da transação imobiliária: **valor do negócio (R$)**, **tipo de transação (Venda vs Locação)** e **temperatura do lead**. Além disso, o rodapé da dashboard decai em um diretório genérico de módulos ("Módulos do Sistema") com textos descritivos que competem com a barra de navegação global. Para o Administrador, o painel exibe métricas de telemetria de servidor ("Sessões Concorrentes", "Eventos Auditados") em vez de indicadores de saúde comercial.

**Deterministic Scan (Detector Automático):**
O scanner estático CLI emitiu 0 alertas primários (`[]`), operando sob modo regex em arquivos `.php`. A inspeção estrutural revelou **falsos negativos críticos** não capturados pelo scanner estático:
1. **Defeito no Alvo HTMX:** Ausência de `hx-swap="outerHTML"` em `#follow-ups-wrapper`, gerando duplicação aninhada de IDs (`<div id="follow-ups-wrapper"><div id="follow-ups-wrapper">...</div></div>`) a cada atualização parcial.
2. **Defeito de Classe CSS:** Uso de `alert-danger` no elemento `#dashboard-error`, classe inexistente no `app.css` (onde a classe correta é `alert-error`), fazendo o alerta de falha de rede renderizar sem cor ou borda de erro.
3. **Quebra de Contraste no Modo Escuro:** Cores inline hardcoded (ex: `#166534` com contraste 1.8:1 no tema escuro) violam as diretrizes WCAG AA.

**Visual Overlays:**
Varredura executada diretamente sobre os templates de renderização server-side do Slim/HTMX.

---

### Overall Impression
O CRM possui um motor operacional muito promissor, ágil e centrado na dor do corretor de não esquecer retornos. No entanto, sofre de **sobrecarga visual por redundância de controles**, **ruptura da continuidade fluida devido a redirects 302 nos modais** e **ausência de parâmetros financeiros do imóvel**, que impedem o corretor de priorizar o que realmente move o faturamento.

---

### What's Working
1. **Atalhos de Alta Velocidade para Contato (`Não Atendeu +2h` e WhatsApp Direto):** Empatia operacional genuína com o dia a dia comercial brasileiro, convertendo uma tarefa de 30 segundos em um reflexo de 200ms com proteção contra duplo clique.
2. **Resiliência de Rede e Feedback de Sistema:** Tratamento de erros de transporte HTMX (`htmx:sendError`, `htmx:responseError`), indicador de carregamento acessível (`role="status"`, `aria-live="polite"`) e diferenciais de tempo amigáveis ("há 2 horas", "em 40 minutos").
3. **Escaneabilidade do Card de Follow-up:** Agrupamento conciso de nome, telefone, etapa do funil, imóvel de interesse e notas em um único bloco visual digerível em menos de 3 segundos.

---

### Priority Issues

#### [P1] Quebra de Continuidade Operacional via Redirect 302 pós-Modal
- **O que é:** Ao salvar o resultado de um contato ou reagendar no modal (`#form-dashboard-complete`), o envio é feito por POST tradicional de navegador e o backend responde com redirecionamento HTTP 302 para `/`.
- **Por que importa:** Quebra o ciclo de dopamina e velocidade. A tela pisca em branco, a rolagem volta ao topo e a visualização reseta para a aba padrão "Hoje". O corretor que estava limpando a lista de atrasados perde a posição na fila e precisa clicar novamente na aba e descer a página.
- **Como corrigir:** Integrar a submissão dos modais via HTMX (`hx-post`, com `hx-target="#follow-ups-wrapper"` e `hx-swap="outerHTML"`), preservando a situação e filtros ativos na resposta.
- **Comando sugerido:** `/impeccable harden`

#### [P1] Redundância e Conflito de Controles de Filtragem
- **O que é:** Os 4 Stat Cards de topo (`views/dashboard/partials/follow_ups_container.php#L28-L108`) e os 5 botões de situação diretamente abaixo deles (`#L127-L166`) manipulam o mesmo parâmetro (`situation`). Além disso, no perfil Corretor, o rodapé repete uma terceira leva de contadores.
- **Por que importa:** Consome mais de 200px de altura útil, gera sobrecarga cognitiva (duas maneiras idênticas de fazer a mesma coisa a 40px de distância) e dilui a área de trabalho dos cartões.
- **Como corrigir:** Unificar em um **Segmented Card Strip** onde os próprios cards de métricas funcionam como abas interativas, eliminando a linha de botões duplicada e os contadores redundantes no rodapé.
- **Comando sugerido:** `/impeccable layout` ou `/impeccable distill`

#### [P1] Alvo de Swap HTMX Aninhando IDs Duplicados e Erro de Classe CSS
- **O que é:** O partial retornado pelo backend já inclui `<div id="follow-ups-wrapper">`. Como o swap padrão do HTMX é `innerHTML`, a cada troca de aba cria-se um `#follow-ups-wrapper` dentro de outro. Adicionalmente, o container de erro `#dashboard-error` referencia a classe inexistente `.alert-danger`.
- **Por que importa:** Degrada a integridade do DOM, impede seletores confiáveis e faz com que falhas de rede sejam exibidas em caixas cinzas sem destaque visual de alerta.
- **Como corrigir:** Adicionar explicitamente `hx-swap="outerHTML"` em todos os gatilhos HTMX do container e corrigir a classe para `alert alert-error`.
- **Comando sugerido:** `/impeccable harden`

#### [P2] Ponto Cego Comercial: Ausência de Valor do Negócio e Tipo de Imóvel
- **O que é:** O card exibe o nome do lead e o nome do imóvel, mas não exibe o valor da transação (R$), se é Venda ou Locação, nem a temperatura do lead.
- **Por que importa:** Um corretor com 12 contatos atrasados não consegue discernir visualmente uma proposta de cobertura de R$ 2,5M de uma dúvida de locação de R$ 1.200, impedindo a priorização por impacto de receita.
- **Como corrigir:** Adicionar uma tag de transação em destaque no topo do card (ex: `<span class="badge badge-deal">R$ 850k • Venda</span>`).
- **Comando sugerido:** `/impeccable clarify` ou `/impeccable bolder`

#### [P2] Incompatibilidade de Contraste em Modo Escuro por Cores Inline
- **O que é:** Aplicação de cores hexadecimais diretas em atributos `style="..."` (ex: `#166534`, `#d97706`, `#4f46e5`).
- **Por que importa:** No tema escuro (`[data-theme="dark"]`), o verde `#166534` atinge contraste de apenas 1.8:1 contra o fundo escuro, tornando o texto praticamente ilegível e violando o critério WCAG AA (mínimo 4.5:1).
- **Como corrigir:** Substituir estilos inline por classes semânticas do `app.css` que já possuem suporte testado e adaptativo ao tema escuro.
- **Comando sugerido:** `/impeccable colorize` ou `/impeccable audit`

---

### Persona Red Flags

* **Marcos (Corretor veterano focado em ligações em lote):**
  * *Ação:* Tenta realizar 8 ligações em sequência nos leads atrasados.
  * *Ruptura:* Ao registrar o resultado no modal, o redirect 302 reseta a tela para a aba "Hoje". Marcos é forçado a reorientar a tela, clicar em "Atrasados" de novo e procurar onde estava.
* **Juliana (Corretora novata buscando bater meta de VGV):**
  * *Ação:* Abre a agenda para escolher as ligações de maior retorno potencial para iniciar o dia.
  * *Ruptura:* Os cards mostram apenas nomes e horários. Juliana precisa abrir 6 abas separadas de leads para descobrir quais têm imóveis de alto valor vinculados antes de discar.
* **Roberto (Diretor Comercial da Imobiliária):**
  * *Ação:* Entra na dashboard para auditar se os corretores estão deixando leads esfriarem.
  * *Ruptura:* Seus 4 cards de estatísticas mostram "Sessões Concorrentes" e "Eventos Auditados". O painel fala com um Sysadmin, não com um gestor de vendas que precisa enxergar leads estagnados e taxa de retorno no prazo.

---

### Minor Observations
- O botão "📞 Não Atendeu (+2h)" tem tipografia de 0.72rem (~11.5px), abaixo do patamar de legibilidade de 12px.
- O campo de busca rápida `filter-q` não possui botão de limpeza imediata ("✕").
- O parágrafo de observações possui `max-height: 4.5rem; overflow-y: auto;`, gerando "scroll-traps" internos em telas intermediárias.
- A barra de navegação no topo agrupa 8 links horizontais sem gaveta móvel em resoluções inferiores a 576px.

---

### Questions to Consider
1. *Por que exigir digitação livre em todo resultado de contato?* Se mais de 70% das ligações imobiliárias resultam em desfechos padronizados ("Caixa postal", "Pediu para ligar mais tarde", "Visita agendada", "Proposta enviada"), por que não oferecer chips rápidos de 1 toque que preenchem a nota automaticamente?
2. *A dashboard deve ser um painel de execução ou um índice de páginas?* Se o corretor precisa agir rápido, por que exibir blocos descritivos de módulos no rodapé em vez de uma fila contínua e dinâmica de oportunidades?
3. *Como o Diretor da Imobiliária pode priorizar gargalos da equipe se ele vê apenas métricas de infraestrutura de servidor?*
