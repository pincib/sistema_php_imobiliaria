---
target: views/leads/import.php
total_score: 36
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 0
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\import.php"
target_fingerprint: "sha256:599e1e93a8b5f4dc4f451f224b896748df05bcb75d96ea5bb64d15774a43e983"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\import.php"
timestamp: 2026-10-23T13-43-00Z
slug: views-leads-import-php
closed: true
---
Method: dual-agent (A: ffbba930-4ba0-4bd1-afff-f827dabfb693 · B: 59b4216f-c109-4a71-9004-e7b7db8794fb)

### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|:-----:|-----------|
| 1 | Visibility of System Status | 4 | Stepper semântico WCAG (`<ol>` / `aria-current`), métricas de impacto no dry-run antes da gravação e identificador de lote exclusivo na conclusão. |
| 2 | Match System / Real World | 4 | Vocabulário da rotina imobiliária brasileira ("Corretor Responsável", "Carteira de Leads", "WhatsApp"), mapeamento flexível de colunas e modelo CSV pronto para download. |
| 3 | User Control and Freedom | 4 | Formulário de cancelamento desacoplado (sem tags `<form>` aninhadas), botão de descarte voluntário em todos os passos e opção de voltar para corrigir mapeamento sem perder o upload. |
| 4 | Consistency and Standards | 3 | Integração nativa com Pico.css via classes de design system (`.import-stat-grid`, `.import-stat-card`), com variáveis semânticas adequadas a temas claro e escuro. |
| 5 | Error Prevention | 4 | Bloqueio de reexecução acidental por hash SHA-256 com confirmação explícita; validação mandatória de nome; limites de 5MB e 5.000 linhas visíveis no envio. |
| 6 | Recognition Rather Than Recall | 4 | Prévia das 5 primeiras linhas da planilha com índices numerados; diagnóstico explícito e transparente de duplicidades e linhas inválidas. |
| 7 | Flexibility and Efficiency | 3 | Detecção automática de delimitador (`,` ou `;`), mapeamento heurístico inteligente e suporte a corretor por linha ou padrão global. |
| 8 | Aesthetic and Minimalist Design | 4 | Hierarquia visual de alta escaneabilidade; dados auxiliares (linhas com erros, duplicidades e histórico de lotes) sob `<details>` colapsáveis. |
| 9 | Error Recovery | 3 | Identificação minuciosa de linhas rejeitadas com número da linha (`Linha #`), nome lido e motivo da recusa (ex: telefone inconsistente, nome ausente). |
| 10 | Help and Documentation | 3 | Botão para "Baixar Modelo (.csv)" direto no navegador, diretrizes de codificação (UTF-8/ISO-8859-1) e explicação textual das regras de duplicidade. |
| **Total** | | **36/40** | **Strong / Production-Ready** |

### Design Specificity Verdict

**LLM assessment**: O assistente atende com exatidão a dor crítica de equipes imobiliárias na migração de carteiras e mailings legados. O dry-run discriminando novos contatos de duplicidades por telefone/e-mail, somado à política transparente de mescla com histórico auditado em `lead_interactions`, entrega uma experiência operacional (Operate) previsível e segura.

**Deterministic scan**: Detector mecânico (`impeccable detect --json`) executado com sucesso e zero violações remanescentes (`[]`). As inconsistências de tags `<form>` aninhadas e contrastes de alerta detectados na varredura inicial foram completamente saneados.

### What's Working
1. **Previsibilidade Absoluta via Dry-Run:** O operador inspeciona a contagem exata de novos leads, contatos existentes a mesclar e linhas recusadas antes de qualquer alteração na base.
2. **Idempotência e Segurança com SHA-256:** Arquivos idênticos reenviados são imediatamente sinalizados com aviso contextual do lote anterior, evitando reprocessamentos desatentos.
3. **Robustez Transacional e Limpeza:** Execução em transação SQLite curta garantindo atomicidade total (zero gravação silenciosa parcial) e expurgo automático dos arquivos temporários de staging.
