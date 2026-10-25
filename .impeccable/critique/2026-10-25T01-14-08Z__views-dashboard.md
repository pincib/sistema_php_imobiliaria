---
p0_count: 0
p1_count: 1
max_score: 40
na_heuristics: 
total_score: 26
target: Resumo principal e dashboards detalhadas de Leads, Imóveis e Proprietários
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\dashboard"
timestamp: 2026-10-25T01-14-08Z
slug: views-dashboard
closed: true
---
# Crítica Impeccable — dashboards do CRM

## Veredito de especificidade
**3/4.** A composição e os dados se relacionam com o uso imobiliário local: agenda antes dos indicadores, estágios reais, relações de Leads e imóveis e acesso às fichas. A linguagem visual ainda depende dos padrões genéricos do Pico.css.

## Heurísticas de Nielsen
1. Visibilidade do estado: 3/4 — loading, período e erros locais presentes; retry do resumo recarrega a página.
2. Mundo real: 3/4 — estágios, unidades e vínculos descritos em termos do CRM.
3. Controle e liberdade: 3/4 — limpar filtros, voltar, fechar detalhe e URLs GET.
4. Consistência: 3/4 — padrões compartilhados; recuperação varia entre atualização parcial e reload.
5. Prevenção: 3/4 — filtros validados e entradas rotuladas.
6. Reconhecimento: 3/4 — escopos de alguns filtros de Proprietários exigem interpretação.
7. Eficiência: 2/4 — filtros e paginação, sem aceleração por lote/atalhos.
8. Minimalismo: 2/4 — resumo adiciona conteúdo depois da agenda e alonga a página inicial.
9. Recuperação: 3/4 — erros locais com retry, mas o resumo recarrega a página.
10. Ajuda: 1/4 — há microcopy, sem ajuda contextual.
**Total: 26/40 — aceitável.**

## Pontos fortes
- Agenda de follow-ups preservada no topo da operação.
- Caminhos de ranking para fichas e registros que compõem os números.
- Filtros, estados vazios e erros locais previstos nos briefs.

## Questões prioritárias
- **P1 — Rotas não disponíveis no container observado:** `/dashboard/leads` retorna 404, embora a rota exista no código. Atualizar/recriar o serviço a partir do checkout e conferir as três rotas.
- **P2 — Escopo dos filtros de Proprietários:** tipo/situação são compartilhados; modalidade afeta somente quantidade; faixa de valor afeta somas. Repetir o escopo nos painéis.
- **P2 — Retry do resumo:** `/#management-summary` recarrega toda a página; considerar nova tentativa HTMX por painel.
- **P2 — Anúncio de atualizações:** indicador de loading existe, mas o conteúdo substituído não declara estado ocupado nem região viva.
- **P3 — Orientação móvel:** a dica menciona deslizar a tabela embora o layout estreito empilhe as células.

## Personas
- **Alex:** filtros e paginação são eficientes; não há atalhos nem ações em lote nas análises.
- **Sam:** semântica dos títulos e rótulos está presente; HTMX pode não anunciar o resultado novo nem preservar foco.
- **Jordan:** explicações ajudam, mas filtros de Proprietários têm escopos diferentes e não há ajuda contextual.

## Evidência e limitações
A rota local `/dashboard/leads` mostrou “404 — Página Não Encontrada”; a raiz redirecionou para `/login`. A análise visual foi feita pelos templates, CSS e briefs. O detector não foi consultado pela Assessment A.

## Run Notes
- Target: `views/dashboard`; slug `views-dashboard`.
- Ignore list: ausente.
- Avaliações independentes: Assessment A (design) e Assessment B (detector/browser) em agentes distintos.
- Detector: `impeccable detect --json views/dashboard`, JSON `[]` (0 achados).
- Browser: aba nova; rota 404; depois login nas rotas operacionais. A tela das dashboards não ficou visível.
- Overlay: preflight não suportado pela superfície CUA, que expõe avaliação somente leitura; sem injeção ou overlay.
- Servidor temporário: nenhum foi iniciado; limpeza/stop não se aplica.
- Arquivos temporários: nenhum pela Assessment A/B.
- Sinal alternativo: inspeção de templates/CSS e briefs porque o container ativo não serve as novas rotas.
