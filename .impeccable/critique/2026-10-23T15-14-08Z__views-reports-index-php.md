---
target: relatórios gerenciais
total_score: 28
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 1
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\reports\\index.php"
target_fingerprint: "sha256:309ae5e8d7c5e4194fa1d519ac95bc2e0e16c48c019948055daf0a1bb9543228"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\reports\\index.php"
timestamp: 2026-10-23T15-14-08Z
slug: views-reports-index-php
closed: true
---
## Design Health Score

**28/40 — Bom.** Avaliação de design baseada no código-fonte; a superfície não foi inspecionada renderizada.

| Heurística | Nota | Observação |
|---|---:|---|
| Visibilidade do estado do sistema | 3/4 | Datas aplicadas e exceção da carteira são explicadas; não há sinalização visível de quando uma exportação alcança o limite de linhas. |
| Correspondência com o mundo real | 3/4 | Vocabulário operacional familiar; `created_at` e `contacted_at` aparecem nas explicações. |
| Controle e liberdade | 3/4 | Aplicar e limpar filtros são ações claras; os filtros ficam preservados para exportação. |
| Consistência e padrões | 3/4 | Filtros, tabelas e navegação seguem padrões previsíveis; detalhes levam às fichas. |
| Prevenção de erros | 3/4 | Datas são obrigatórias e o limite de período é documentado; o limite da exportação não tem feedback contextual. |
| Reconhecimento em vez de lembrança | 3/4 | Período e filtros ficam visíveis; definições detalhadas ficam recolhidas. |
| Flexibilidade e eficiência | 2/4 | Exportar por conjunto ajuda; faltam atalhos de cada indicador à sua tabela de origem. |
| Estética e minimalismo | 3/4 | Seções e tabelas são organizadas; há muitos blocos verticais para uma revisão rápida. |
| Reconhecer, diagnosticar e recuperar de erros | 2/4 | Filtros inválidos geram alerta, mas não mostram qual período padrão foi aplicado. |
| Ajuda e documentação | 3/4 | Definições e limites estão na página, separados da ação de exportar. |

### Especificidade do design

A superfície é própria da operação imobiliária: “Caderno de negócios”, estágio e responsável atuais, leads e histórico de interações dão contexto. A interface operacional favorece conferência, embora a ligação entre cada total e seus registros pudesse ser mais imediata.

### Carga cognitiva e jornada

Carga moderada: cinco controles e duas ações compartilham uma faixa de filtros. O gestor compreende a distinção entre atividade no período e carteira atual; a confiança diminui ao validar os totais rolando por várias tabelas e quando um filtro inválido não revela o intervalo usado. O teto de exportação está documentado, mas distante do controle.

### Pontos fortes

- Distingue atividade datada da fotografia atual da carteira.
- Define datas inclusivas, filtros atuais e limite de período.
- IDs e links para fichas apoiam a conferência dos registros de origem.

### Problemas prioritários

1. **P1 — Exportação limitada sem retorno contextual.** O limite de 5.000 linhas está documentado, mas não há indicação junto ao controle de que a extração atingiu o teto. Exibir total exportado e sinalizar quando houver truncamento para evitar que um arquivo parcial pareça completo.
2. **P2 — Filtro inválido não revela o período aplicado.** O alerta menciona o padrão, mas não as datas resultantes. Mostrar o intervalo efetivo no alerta e junto aos indicadores.
3. **P2 — Tabelas de origem distantes dos totais.** Adicionar em cada indicador um link direto para os registros que o compõem.
4. **P2 — Limite afastado da exportação.** Repetir junto ao botão o aviso sobre o teto de linhas.

### Personas e riscos

- **Alex, gestor experiente:** precisa rolar até as tabelas de origem; links por indicador encurtam a validação.
- **Sam, usuário de teclado/leitor de tela:** a semântica de formulários, seções e tabelas parece adequada no markup; o alerta precisa explicitar as datas aplicadas.
- **Jordan, primeiro uso:** a definição de interações está disponível, mas exige abrir a seção recolhida para confirmar a regra de contagem.

### Observações menores

- “Interações por tipo” usa os mesmos filtros, mas a associação ao total depende do texto explicativo.
- “Abrir leads” não conserva os filtros aplicados.
- A sequência de tabelas pode demandar rolagem considerável.

### Perguntas provocativas

- Como o gestor distingue uma extração completa de uma limitada a 5.000 linhas?
- Cada total pode levar diretamente aos registros que o compõem?
- O intervalo padrão aplicado após erro deveria aparecer no próprio alerta?
