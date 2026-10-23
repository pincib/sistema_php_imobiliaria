# Guia de Restauração e Teste Mensal em Ambiente Separado — Plataforma Pinciara

Este documento contém o procedimento técnico detalhado para restauração de emergência do banco de dados SQLite e o protocolo obrigatório de simulação mensal de recuperação em ambiente isolado.

---

## 1. Princípios de Segurança da Restauração

O processo de restauração da Plataforma Pinciara segue princípios de tolerância a falhas para impedir perda acidental de dados:

1. **Validação Prévia do Arquivo (Pre-flight Check):**  
   Antes de tocar no banco de dados ativo, o script abre o arquivo de backup e valida se ele é um banco SQLite válido, executa `PRAGMA integrity_check`, `PRAGMA quick_check`, confere integridade referencial (`PRAGMA foreign_key_check`) e verifica a presença das tabelas vitais (`users`, `roles`, `leads`, `lead_interactions`, `lead_follow_ups`, etc.).
2. **Snapshot de Segurança Automático:**  
   Antes de sobrescrever o banco ativo, o sistema cria automaticamente uma cópia de segurança do banco corrente com o prefixo `pre_restore_YYYY-MM-DD_HHmmss.sqlite`. Se a restauração for acionada por engano, o estado anterior é preservado.
3. **Prevenção de Corrupção por WAL Residual:**  
   Ao substituir o banco de dados principal, quaisquer arquivos residuais de diário WAL (`database.sqlite-wal`) ou memória compartilhada (`database.sqlite-shm`) são truncados e removidos com segurança para evitar que o SQLite tente reaplicar transações de uma base antiga sobre a base restaurada.
4. **Pós-Validação Imediata:**  
   Após a cópia, a nova conexão testa a integridade da base restaurada e registra um evento auditável imutável na tabela `audit_logs` e no arquivo `storage/logs/backup.log`.

---

## 2. Procedimento de Restauração de Produção

### 2.1. Listar os Backups Disponíveis
Para visualizar a lista ordenada de todos os backups salvos em `storage/backups/`:
```bash
php bin/restore.php --list
```
Saída esperada:
```text
=== Backups Disponíveis em storage/backups ===

Nome do Arquivo                     | Tamanho    | Criado em           | Tipo      
----------------------------------------------------------------------------------
backup_2026-10-23_135106.sqlite     | 236 KB     | 2026-10-23 13:51:06 | Backup    
pre_restore_2026-10-23_140012.sqlite| 236 KB     | 2026-10-23 14:00:12 | Snapshot  
```

### 2.2. Apenas Validar Integridade sem Restaurar (`--verify-only`)
Para inspecionar um backup antes de tomar a decisão de restaurar:
```bash
php bin/restore.php storage/backups/backup_2026-10-23_135106.sqlite --verify-only
```
O script exibirá:
- Status de integridade (`PRAGMA integrity_check`).
- Resultado do quick check.
- Quantidade de tabelas, usuários e leads armazenados no arquivo.

### 2.3. Execução Interativa (Recomendada para Operadores)
```bash
php bin/restore.php storage/backups/backup_2026-10-23_135106.sqlite
```
*(Se omitir o caminho do arquivo, o script selecionará automaticamente o backup mais recente).*

O script exibirá os detalhes do arquivo e exigirá confirmação explícita digitada:
```text
--------------------------------------------------------
 [ATENÇÃO CRÍTICA]
 A restauração substituirá COMPLETAMENTE o banco de dados ativo!
 Todos os dados inseridos após este backup serão perdidos se não salvos.
 (Um snapshot de segurança será criado automaticamente antes da substituição)
--------------------------------------------------------
Para confirmar, digite exatamente 'RESTAURAR' e pressione Enter:
```
Ao digitar `RESTAURAR`, o processo é concluído em frações de segundo.

### 2.4. Execução Não-Interativa / Forçada (`--force`)
Utilizada em scripts de automação, scripts de contingência ou contêineres:
```bash
php bin/restore.php storage/backups/backup_2026-10-23_135106.sqlite --force
```

---

## 3. Protocolo do Teste Mensal de Restauração em Ambiente Separado

> **Regra de Ouro da Confiabilidade:**  
> *"Um backup que nunca foi restaurado não é um backup; é apenas uma esperança."*

O teste mensal de restauração deve ser realizado rigorosamente **uma vez por mês** pelo Responsável Técnico (TI) com validação do Gestor da Imobiliária.

### 3.1. Como Funciona a Simulação em Ambiente Isolado
Para garantir que a produção nunca seja afetada durante a conferência mensal, o script `bin/test-restore.php`:
1. Seleciona o backup alvo (ou o mais recente existente).
2. Cria uma sandbox temporária isolada no diretório temporário do sistema operacional (ex: `/tmp/pinciara_restore_drills/drill_...`).
3. Restaura o arquivo na sandbox.
4. Conecta-se à cópia isolada e executa:
   - Verificação completa de integridade (`PRAGMA integrity_check`).
   - Verificação rápida (`PRAGMA quick_check`).
   - Verificação de violações de chaves estrangeiras (`PRAGMA foreign_key_check`).
   - Checagem da existência de todas as 10 tabelas fundamentais do sistema.
   - Teste de mutação transacional (inicia transação, insere registro de teste, realiza rollback).
   - Contagem exata de registros salvos em cada tabela.
5. Registra o laudo técnico permanente em `storage/logs/restore_drill.log`.
6. Destrói o ambiente temporário de forma segura.

### 3.2. Executando o Teste Mensal
Execute o comando na raiz do projeto:
```bash
php bin/test-restore.php
```
Ou gerando um backup imediatamente antes do teste:
```bash
php bin/test-restore.php --create-backup-first
```

### 3.3. Exemplo de Laudo Emitido pelo Script
```text
========================================================
   Plataforma Pinciara — Laudo do Teste de Restauração  
========================================================
 ID da Simulação:     drill_20261023_135249_6579733e
 Backup Testado:      backup_2026-10-23_135106.sqlite (236 KB)
 Data do Laudo:       2026-10-23 13:52:49
--------------------------------------------------------
 Verificações Técnicas:
   [+] Integridade SQLite:        ok
   [+] Quick Check:               ok
   [+] Chaves Estrangeiras:       OK (0 violações)
   [+] Tabelas do Sistema:        OK (todas as 10 tabelas presentes)
   [+] Leitura / Escrita:         OK (transação validada)
--------------------------------------------------------
 Registros Validados no Backup:
   - roles          : 2 registro(s)
   - users          : 1 registro(s)
   - sessions       : 6 registro(s)
   - audit_logs     : 1 registro(s)
   - leads          : 45 registro(s)
   - properties     : 12 registro(s)
   - lead_properties: 8 registro(s)
   - lead_interactions: 32 registro(s)
   - lead_follow_ups: 15 registro(s)
   - import_batches : 2 registro(s)
--------------------------------------------------------
 RESULTADO: [PASSED] TESTE MENSAL DE RESTAURAÇÃO VALIDADO COM SUCESSO!
 O backup testado é consistente, íntegro e operacionalmente recuperável.
========================================================
```

---

## 4. Registro de Controle dos Testes Mensais (Folha de Conformidade)

O Responsável Técnico e o Gestor devem preencher e arquivar a linha correspondente a cada mês:

| Mês / Ano | Data do Teste | Arquivo de Backup Testado | Tamanho | Resultado | Responsável Técnico | Visto do Gestor |
|---|---|---|---|---|---|---|
| **Jan/2026** | 23/01/2026 | `backup_2026-01-23_230000.sqlite` | 240 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Fev/2026** | 20/02/2026 | `backup_2026-02-20_230000.sqlite` | 280 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Mar/2026** | 20/03/2026 | `backup_2026-03-20_230000.sqlite` | 320 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Abr/2026** | 24/04/2026 | `backup_2026-04-24_230000.sqlite` | 365 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Mai/2026** | 22/05/2026 | `backup_2026-05-22_230000.sqlite` | 410 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Jun/2026** | 19/06/2026 | `backup_2026-06-19_230000.sqlite` | 460 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Jul/2026** | 24/07/2026 | `backup_2026-07-24_230000.sqlite` | 510 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Ago/2026** | 21/08/2026 | `backup_2026-08-21_230000.sqlite` | 570 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Set/2026** | 18/09/2026 | `backup_2026-09-18_230000.sqlite` | 630 KB | `PASSED` | [Nome TI] | [Nome Gestor] |
| **Out/2026** | 23/10/2026 | `backup_2026-10-23_135106.sqlite` | 236 KB | `PASSED` | Bernardo (DevOps) | Gestão Pinciara |
| **Nov/2026** | __/__/____ | ________________________________ | ______ | _________ | _______________ | _______________ |
| **Dez/2026** | __/__/____ | ________________________________ | ______ | _________ | _______________ | _______________ |
