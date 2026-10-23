# Revisão dos Critérios e Validação de Saída do MVP — Plataforma Pinciara

Este documento consolida a revisão técnica e operacional dos 5 pilares do MVP (Mínimo Produto Viável) e comprova o atendimento integral aos **Critérios de Saída do MVP**, conforme especificado em `architecture/architecture_v2.md` e `planning/roadmap.md`.

---

## 1. Revisão Detalhada dos 5 Pilares do MVP

### Pilar 1: Importação de Planilhas Legadas via CSV
- **Objetivo:** Permitir que a imobiliária migre bases dispersas com previsibilidade, validação e rastreabilidade por lote.
- **Implementação:**
  - **Fluxo em 5 Passos:** Upload seguro do arquivo → Prévia dos dados → Mapeamento flexível de colunas → Validação prévia de tipos → Importação atômica em lote (`import_batches`).
  - **Deduplicação e Conflito:** Política explícita de duplicidade (`merge` por telefone ou e-mail coincidente), atualizando o lead e registrando o contato como evento de histórico, sem duplicar registros.
  - **Limites e Proteção:** Validação de tamanho máximo, tipos MIME, cabeçalhos inválidos e descarte de linhas malformadas com relatório detalhado de erros por linha.
  - **Rastreabilidade:** Cada lead importado guarda a referência `import_batch_id`, permitindo consultar exatamente quando, quem e a partir de qual arquivo o lead entrou na base.
- **Evidências:** `src/Modules/Leads/Services/LeadImportService.php`, `tests/Feature/LeadImportWorkflowTest.php`.

---

### Pilar 2: Registro Cronológico de Interações
- **Objetivo:** Fornecer histórico completo de conversas e atendimentos para cada oportunidade, evitando perda de contexto.
- **Implementação:**
  - **Tabela Dedicada:** `lead_interactions`, associando `lead_id`, `user_id` (autor), `type` (ligação, WhatsApp, e-mail, visita, reunião, anotação), `notes` e `contacted_at`.
  - **Integridade Referencial:** Chave estrangeira com `ON DELETE CASCADE` para o lead e `ON DELETE RESTRICT` para o usuário autor (preserva histórico de ex-funcionários).
  - **Idempotência:** Campo `idempotency_key` prevenindo duplo clique ou reenvios acidentais na rede local.
  - **Visualização:** Linha do tempo cronológica decrescente na tela de detalhe do lead com identificação clara do autor e tempo relativo decorrido.
- **Evidências:** `src/Modules/Leads/Services/InteractionService.php`, `tests/Unit/InteractionServiceTest.php`.

---

### Pilar 3: Controle e Lembretes de Follow-Up
- **Objetivo:** Assegurar que nenhum lead fique abandonado por esquecimento de retorno pela equipe comercial.
- **Implementação:**
  - **Campo Central:** `next_follow_up_at` sincronizado no lead e registrado na tabela `lead_follow_ups`.
  - **Dashboard Operacional:** Segregação visual imediata em 3 abas essenciais: **Atrasados** (vermelho), **Do Dia** (destaque operacional) e **Futuros**.
  - **Ciclo de Vida do Retorno:** Ações com 1 clique para **Concluir** (com registro de notas do resultado) ou **Reagendar** (com nova data/hora).
  - **Fuso Horário Local:** Suporte consistente a `America/Sao_Paulo` via `DateHelper`.
- **Evidências:** `src/Modules/Leads/Services/FollowUpService.php`, `tests/Feature/FollowUpWorkflowTest.php`, `tests/Feature/FollowUpHardeningTest.php`.

---

### Pilar 4: Trilha de Auditoria Imutável (Audit Trail)
- **Objetivo:** Registrar quem fez o quê, quando e de onde, garantindo total conformidade e governança.
- **Implementação:**
  - **Serviço Centralizado:** `AuditService::log()` gravando na tabela `audit_logs`.
  - **Eventos Monitorados:** Autenticação (login, falha de login, logout), criação de usuários, desativação de contas, criação/edição de leads, trocas de estágio (com motivo), reatribuição de corretor, importações CSV e rotinas de backup/restauração.
  - **Captura Completa:** Usuário autor, data/hora, endereço IP, User-Agent e diferencial em JSON (`old_values` e `new_values`).
  - **Interface Restrita:** Painel `/admin/audit` acessível exclusivamente pelo perfil `admin`, com filtros por ação e paginação no servidor.
- **Evidências:** `src/Audit/AuditService.php`, `src/Modules/Admin/Controllers/AuditController.php`.

---

### Pilar 5: Backup Consistente e Restaurável
- **Objetivo:** Garantir a sobrevivência dos dados e a continuidade do negócio em caso de falha física do host ou exclusão acidental.
- **Implementação:**
  - **Mecanismo Seguro:** SQLite `VACUUM INTO` com verificação imediata de `PRAGMA integrity_check`.
  - **Retenção Automatizada:** Rotação configurável (mínimo de 14 backups mantidos e expurgo de arquivos com mais de 30 dias).
  - **Proteção Pré-Restauração:** Snapshot automático de segurança (`pre_restore_...`) antes de qualquer substituição de banco.
  - **Tratamento de WAL:** Truncamento e limpeza garantida dos arquivos `-wal` e `-shm` na restauração para eliminar risco de corrupção ou replay indesejado de transações.
  - **Cópia Off-Host:** Suporte a destino externo (pendrive / drive mapeado) com validação de checksum SHA-256.
  - **Painel e Ferramentas:** CLI (`bin/backup.php`, `bin/restore.php`, `bin/test-restore.php`, `bin/health-check.php`) e interface gráfica em `/admin/system`.
- **Evidências:** `src/Backup/BackupService.php`, `tests/Unit/BackupServiceTest.php`, `tests/Feature/BackupRestoreTest.php`.

---

## 2. Validação dos Critérios de Saída do MVP

| Critério de Saída Exigido | Status | Evidência Comprovada |
|---|---|---|
| **1. Uma restauração foi demonstrada e validada** | **CONCLUÍDO (VALIDADO)** | Teste funcional automatizado de ponta a ponta (`BackupRestoreTest.php`) executado com sucesso: geração de backup, deleção total de dados, restauração atômica, verificação de 100% dos dados recuperados e 0 violações de FK. Execução da simulação isolada via `bin/test-restore.php` gerando laudo técnico `[PASSED]`. |
| **2. Há responsável definido por Docker, contas, atualizações e backups** | **CONCLUÍDO (DEFINIDO)** | Matriz RACI formalmente documentada em `docs/OPERATIONS.md`, segregando as responsabilidades entre o Responsável Técnico de TI (Docker, infraestrutura, atualizações, backups) e o Gestor da Imobiliária (contas, papéis, auditoria e custódia externa). |
| **3. Fluxos principais foram percorridos com perfis Admin e Corretor, incluindo falhas comuns** | **CONCLUÍDO (PERCORRIDO)** | Suíte de testes `MvpWalkthroughTest.php` percorrendo todas as jornadas de Admin e Corretor, validando bloqueio 403 para corretores em áreas administrativas, rejeição de login para usuários desativados, bloqueio de transição para 'perdido' sem motivo, e rejeição de backups corrompidos. |

---

## 3. Resumo da Suíte de Testes Automatizados

O sistema conta atualmente com **90 testes automatizados e 481 asserções**, cobrindo 100% dos módulos do MVP:

```text
PHPUnit 10.5.66 by Sebastian Bergmann and contributors.

Configuration: phpunit.xml

................................................................. 65 / 90 ( 72%)
.........................                                         90 / 90 (100%)

Time: 00:12.939, Memory: 14.00 MB

OK (90 tests, 481 assertions)
```

### Detalhamento das Suítes:
- `tests/Unit/BackupServiceTest.php`: 6 testes unitários cobrindo `VACUUM INTO`, integridade, rotação, cópia off-host e rejeição de corrupção.
- `tests/Feature/BackupRestoreTest.php`: Teste de recuperação de desastres com entidades reais restauradas.
- `tests/Feature/MvpWalkthroughTest.php`: 7 testes de fluxo completo (Admin, Corretor, falhas comuns, CSRF, permissões).
- `tests/Feature/LeadImportWorkflowTest.php`: Fluxo completo de importação CSV em 5 passos.
- `tests/Feature/FollowUpWorkflowTest.php` e `FollowUpHardeningTest.php`: Fluxos de retorno, conclusão e concorrência.
- `tests/Feature/LeadManagementTest.php` e `RouteAuthorizationTest.php`: Gestão de leads, máquina de estados do pipeline e RBAC.
- `tests/Feature/AuthSecurityTest.php`: Autenticação segura, hashes e proteção de sessão.

