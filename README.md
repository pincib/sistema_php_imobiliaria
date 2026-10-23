# Plataforma Pinciara — CRM Imobiliário Local-First

Sistema de CRM imobiliário minimalista, de alta agilidade e soberania de dados local, projetado para operar na rede local da imobiliária via Docker (Apache + mod_php + SQLite) com renderização server-side via HTMX e Slim 4.

---

## 🚀 Requisitos e Stack Tecnológica

- **PHP**: 8.2+ (com extensões `pdo_sqlite`, `sqlite3`, `mbstring`, `zip`)
- **Composer**: 2.x
- **Servidor Web / Container**: Docker com Apache + mod_php
- **Banco de Dados**: SQLite 3 (WAL Mode, `foreign_keys=ON`, `busy_timeout=5000`)
- **Estilo & UI**: Pico.css v2 (hospedado localmente para operação 100% offline) + HTMX

---

## 📁 Estrutura do Projeto

```text
plataforma_pinciara-teste_02/
├── bin/                         # Comandos CLI de operação
├── database/migrations/         # Migrações SQL versionadas
├── docs/
│   ├── architecture/            # Arquitetura e modelo de dados
│   ├── planning/                # Roadmap e planejamento
│   ├── requirements/            # Especificações funcionais
│   └── ORGANIZACAO.md           # Mapa detalhado do repositório
├── output/
│   ├── pdf/                     # Documentos finais
│   └── previews/                # Pré-visualizações por finalidade
├── public/                      # Único diretório servido pelo Apache
├── src/                         # Código PHP da aplicação
├── storage/                     # Banco local, backups e logs
├── tests/                       # Testes unitários e funcionais
├── tools/documents/             # Gerador do manual PDF
├── views/                       # Templates agrupados por área
├── planilhas_csv_import/        # CSVs de origem da importação
├── PRODUCT.md                   # Contexto de produto
├── README.md                    # Instalação e visão geral
├── Dockerfile                   # Imagem da aplicação
└── docker-compose.yml           # Execução local em contêiner
```

---

## 🔒 Segurança e Isolamento

1. **Proteção contra Exposição do Banco de Dados**:
   - O Apache aponta exclusivamente para o diretório `public/`.
   - O banco SQLite (`storage/database.sqlite`), os códigos em `src/`, as migrações e o `.env` ficam fisicamente **fora do DocumentRoot**.
   - Defesa em profundidade: `storage/.htaccess` impõe `Require all denied` para bloquear qualquer leitura direta.
2. **Sessões e Cookies Reforçados**:
   - `HttpOnly`: cookies de sessão inacessíveis via JavaScript.
   - `SameSite=Lax`: mitiga ataques de Cross-Site Request Forgery em navegação.
   - `session.use_strict_mode = 1` e `session_regenerate_id(true)` no login para prevenir *Session Fixation*.
   - Sessões gravadas na tabela `sessions` do SQLite, possibilitando auditoria, persistência entre reinicializações e encerramento forçado de sessões de usuários desativados.
3. **Proteção CSRF**:
   - Token criptográfico de 32 bytes validado em tempo constante (`hash_equals()`).
   - Aplicado a todos os métodos de mutação (`POST`, `PUT`, `DELETE`, `PATCH`).
   - Suporte transparente a formulários convencionais (`_csrf_token`) e cabeçalho `X-CSRF-Token` para HTMX.
4. **Controle de Acesso Baseado em Papéis (RBAC)**:
   - Papéis fundamentais: `admin` (Administrador) e `corretor` (Corretor).
   - `AuthMiddleware` garante autenticação ativa em rotas privadas.
   - `RoleMiddleware` restringe endpoints administrativos e registra tentativas de acesso não autorizado na auditoria.

---

## 🛠️ Instalação e Execução

### Opção 1: Via Docker (Recomendado para Produção / Rede Local)

1. Crie o arquivo `.env` a partir do modelo:
   ```bash
   cp .env.example .env
   ```
2. Inicialize o contêiner com Docker Compose:
   ```bash
   docker compose up -d --build
   ```
3. Execute as migrações dentro do contêiner:
   ```bash
   docker compose exec app php bin/migrate.php
   ```
4. Crie o primeiro administrador do sistema:
   ```bash
   docker compose exec app php bin/create-admin.php
   ```
5. Acesse no navegador:
   `http://localhost:8086`

---

### Opção 2: Desenvolvimento Local (PHP CLI)

1. Instale as dependências:
   ```bash
   composer install
   ```
2. Execute as migrações no banco SQLite:
   ```bash
   php bin/migrate.php
   ```
3. Crie o primeiro administrador:
   ```bash
   php bin/create-admin.php
   ```
4. Inicie o servidor embutido apontando para a pasta pública:
   ```bash
   php -S 127.0.0.1:8080 -t public
   ```

---

## 👤 Criação Segura do Primeiro Administrador

O script `bin/create-admin.php` foi desenvolvido **sem nenhuma senha padrão embutida**:

- **Modo Interativo**:
  ```bash
  php bin/create-admin.php
  ```
  O script solicita nome e e-mail. Se o campo de senha for deixado em branco, uma **senha aleatória segura de 16 caracteres** é gerada criptograficamente e exibida uma única vez no terminal.

- **Modo Argumentos**:
  ```bash
  php bin/create-admin.php --name="Administrador" --email="admin@pinciara.com" --password="SuaSenhaForteAqui"
  ```

---

## 💾 Rotina de Backups e Restauração

O CRM conta com suporte nativo a backups atômicos via SQLite `VACUUM INTO`, retenção automática e restauração segura com snapshot prévio:

### 1. Gerar Backup Consistente
```bash
php bin/backup.php
```
- Destino padrão: `storage/backups/backup_YYYY-MM-DD_HHmmss.sqlite`.
- Proteção por `.htaccess` contra acesso via web.
- Rotação automática: mantém no mínimo 14 backups e expurga excedentes com mais de 30 dias.
- Suporte a cópia em disco externo/pendrive (`BACKUP_EXTERNAL_DIR` no `.env`).

### 2. Restaurar um Backup
```bash
# Modo Interativo (solicita confirmação de segurança)
php bin/restore.php storage/backups/backup_2026-10-23_135106.sqlite

# Listar backups disponíveis
php bin/restore.php --list

# Validar integridade sem aplicar
php bin/restore.php storage/backups/backup_2026-10-23_135106.sqlite --verify-only

# Modo Automação / Forçado
php bin/restore.php storage/backups/backup_2026-10-23_135106.sqlite --force
```
*(Antes de qualquer restauração, o sistema cria um snapshot automático `pre_restore_...` do banco atual e trunca com segurança os arquivos WAL).*

### 3. Teste Mensal de Restauração em Ambiente Isolado
```bash
php bin/test-restore.php
```
Executa simulação técnica isolada no diretório temporário, valida `PRAGMA integrity_check`, chaves estrangeiras, transações de leitura/escrita e emite laudo formal arquivado em `storage/logs/restore_drill.log`.

---

## 📊 Diagnóstico e Monitoramento

Execute o diagnóstico do sistema para validar disponibilidade, integridade do SQLite WAL, espaço em disco e frescor dos backups:
```bash
# Diagnóstico amigável no terminal
php bin/health-check.php

# Saída estruturada em JSON (para sondas e cron)
php bin/health-check.php --json
```

Administradores também podem acompanhar a saúde e acionar backups diretamente na interface web através do menu **Sistema** (`/admin/system`).

---

## 📚 Documentação

- [Mapa da organização do repositório](docs/ORGANIZACAO.md): Estrutura de pastas, finalidade dos arquivos e regras para novos materiais.
- [Arquitetura do sistema](docs/architecture/architecture_v2.md): Stack, módulos e modelo de dados.
- [Roadmap do produto](docs/planning/roadmap.md): Fases de desenvolvimento e planejamento.
- [Requisitos e melhorias](docs/requirements/newinformations.md): Especificação funcional organizada por fases.
- [Manual Operacional e Matriz RACI](docs/OPERATIONS.md): Responsabilidades, procedimentos de atualização, migrações e playbook de recuperação de desastres (falha do host).
- [Guia de Restauração e Teste Mensal](docs/RESTORATION.md): Passo a passo para operadores e protocolo de conferência periódica.
- [Revisão dos Critérios do MVP](docs/MVP_REVIEW.md): Análise dos 5 pilares do MVP e evidências dos critérios de saída.

---

## 🧪 Suíte de Testes Automatizados

A aplicação inclui testes unitários e de integração cobrindo conexão SQLite (WAL, FK, timeout), idempotência de migrações, autenticação, CSRF, permissões de rota, auditoria, importação CSV, follow-ups e fluxo de backup/restauração.

Para executar todos os testes:
```bash
vendor/bin/phpunit
```


