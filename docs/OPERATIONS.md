# Manual Operacional e Procedimentos de Sustentação — Plataforma Pinciara

Este documento estabelece as diretrizes operacionais, a matriz de responsabilidades e os procedimentos padrão de manutenção, atualização, gestão de usuários, monitoramento e recuperação de desastres do CRM Imobiliário Local-First da Plataforma Pinciara.

---

## 1. Matriz de Responsabilidade (RACI)

Para assegurar que o CRM permaneça estável, seguro e recuperável no cotidiano da imobiliária, os seguintes papéis e atribuições operacionais estão formalmente definidos:

| Atividade / Área | Responsável Técnico (TI / DevOps / Sysadmin) | Gestor da Imobiliária (Administrador do Negócio) | Corretor Comercial (Usuário Operacional) |
|---|---|---|---|
| **Ambiente Docker & Host** | **R** (Responsável pela execução) | **A** (Aprova e fiscaliza) | **I** (Informado) |
| **Contas e Usuários** | **C** (Consultado para reset crítico) | **R / A** (Cria, altera papéis e desativa) | **I** (Acessa o sistema) |
| **Atualizações e Código** | **R** (Executa pulls, migrações e builds) | **A** (Valida impacto no negócio) | **I** (Informado de paradas) |
| **Rotina Diária de Backups** | **R** (Configura cron/scheduler e monitora) | **A** (Assegura cópia externa em pendrive/drive) | **-** |
| **Teste Mensal de Restauração**| **R** (Executa simulação técnica isolada) | **A** (Valida registros e assina laudo) | **-** |
| **Recuperação de Falha do Host**| **R** (Executa disaster recovery na nova máquina) | **A** (Fornece máquina e chave de backup) | **I** (Retoma atendimento) |

> **Legenda:**  
> **R** = *Responsible* (quem executa a tarefa técnica).  
> **A** = *Accountable* (quem responde pelo sucesso da operação).  
> **C** = *Consulted* (quem apoia com informações técnicas).  
> **I** = *Informed* (quem é comunicado sobre a atividade).

---

## 2. Rotina de Backup e Retenção

### 2.1. Princípio do `VACUUM INTO`
Diferente de cópias diretas de arquivo que podem capturar um banco corrompido em momento de escrita ou deixar arquivos de log WAL (`-wal`) inconsistentes, o backup da Plataforma Pinciara utiliza a instrução atômica do SQLite:
```sql
VACUUM INTO 'storage/backups/backup_YYYY-MM-DD_HHmmss.sqlite'
```
Essa operação:
1. Bloqueia transações concorrentes pelo tempo estritamente necessário (fração de segundo).
2. Defragmenta o banco de dados.
3. Incorpora todas as alterações pendentes do diário WAL em um arquivo `.sqlite` único, autônomo e consistente.
4. Executa imediatamente `PRAGMA integrity_check` para garantir que o backup gerado não possui corrupção.

### 2.2. Execução Manual via CLI
Dentro da raiz do projeto (no host ou dentro do contêiner):
```bash
# Execução direta pelo PHP CLI
php bin/backup.php

# Ou via Docker Compose
docker compose exec app php bin/backup.php
```

Parâmetros disponíveis:
- `--keep=14`: Número mínimo de backups preservados na rotação.
- `--retention=30`: Prazo de retenção em dias (backups excedentes mais antigos que isso são expurgados).
- `--external-dir=/caminho/externo`: Destino espelho (pendrive, drive mapeado SMB/NFS).
- `--no-rotation`: Pula o expurgo nesta execução.
- `--json`: Saída estruturada em JSON para sondas e monitoramento.

### 2.3. Agendamento Automático Diário

#### No Linux (Cron do Host)
Edite a crontab do host (`crontab -e`):
```bash
# Executa todo dia às 23:00 com rotação automática
0 23 * * * cd /caminho/plataforma_pinciara && docker compose exec -T app php bin/backup.php >> /var/log/pinciara_cron.log 2>&1
```

#### No Windows (Agendador de Tarefas / Task Scheduler)
1. Abra o **Agendador de Tarefas** (`taskschd.msc`).
2. Crie uma **Tarefa Básica** com disparador diário às 23:00.
3. Ação: **Iniciar um programa**.
   - Programa/script: `docker`
   - Argumentos: `compose exec -T app php bin/backup.php`
   - Iniciar em: `C:\Docker\plataforma_pinciara-teste_02`

### 2.4. Cópia Fora do Host (Off-Host Mirroring)
Se a imobiliária definir uma política de cópia externa (ex: pendrive diário ou compartilhamento de rede montado):
1. Configure a variável no `.env`:
   ```dotenv
   BACKUP_EXTERNAL_DIR=/mnt/backup_externo/pinciara
   ```
2. A cada execução de `php bin/backup.php`, o script copia o arquivo recém-gerado para o destino externo e valida o hash SHA-256 da cópia. Se o drive não estiver inserido, o script registra um aviso no log sem interromper o backup local.

---

## 3. Procedimento de Atualização da Aplicação

Ao aplicar novas versões, melhorias de tela ou correções do sistema:

### Passo 1: Backup de Segurança Obrigatório
Antes de qualquer alteração de código ou contêiner:
```bash
php bin/backup.php
```
Certifique-se de que a mensagem `[SUCCESS]` foi exibida no terminal.

### Passo 2: Atualização do Código-Fonte
```bash
# 1. Puxe as atualizações do repositório
git pull origin main

# 2. Atualize dependências do Composer (se houver alteração em composer.lock)
composer install --no-dev --optimize-autoloader
```

### Passo 3: Execução de Migrações de Banco
```bash
php bin/migrate.php
```
O script informará se há migrações pendentes e as aplicará sequencialmente dentro de transações protegidas.

### Passo 4: Reinicialização dos Contêiners Docker
```bash
docker compose down
docker compose up -d --build
```

### Passo 5: Verificação Pós-Atualização (Smoke Test)
1. Execute o diagnóstico de integridade:
   ```bash
   php bin/health-check.php
   ```
2. Acesse a rota de saúde no navegador: `http://localhost:8080/health`.
3. Faça login com perfil Admin e verifique o painel em `/admin/system`.

---

## 4. Procedimento de Migrações de Banco de Dados

Todas as alterações estruturais no SQLite devem ser realizadas exclusivamente por arquivos de migração cronológicos na pasta `database/migrations/`.

### 4.1. Convenção de Nomes
```text
database/migrations/XXX_descricao_da_mudanca.sql
```
Exemplo: `007_add_contract_fields_to_properties.sql`.

### 4.2. Diretrizes de Idempotência
1. Sempre utilize `CREATE TABLE IF NOT EXISTS` ou `CREATE INDEX IF NOT EXISTS`.
2. Em SQLite, para adicionar colunas em tabelas existentes:
   ```sql
   ALTER TABLE leads ADD COLUMN contract_number TEXT;
   ```
3. Todas as migrações aplicadas são registradas na tabela de controle de versão interna do migrador, garantindo que nunca sejam reexecutadas no mesmo banco.

### 4.3. Execução das Migrações
```bash
php bin/migrate.php
```

---

## 5. Procedimento de Gestão de Usuários e Acessos

### 5.1. Criação Segura do Primeiro Administrador
Para novas instalações, utilize o script CLI que gera senhas criptográficas fortes sem senhas padrão embutidas:
```bash
php bin/create-admin.php
```
Ou passando argumentos diretos:
```bash
php bin/create-admin.php --name="Administrador TI" --email="admin@pinciara.com" --password="SuaSenhaSeguraAqui123"
```

### 5.2. Gestão pela Interface Web (Admin)
1. Acesse o menu **Usuários** (`/admin/users`).
2. **Criação de novos usuários**: Informe nome, e-mail comercial e senha (mínimo de 8 caracteres).
3. **Ajuste de Papel**:
   - `admin`: Acesso completo ao sistema, usuários, auditoria, importação CSV e monitoramento.
   - `corretor`: Acesso operacional a leads, imóveis, placas, interações e follow-ups. Bloqueado de endpoints administrativos (HTTP 403).
4. **Desativação de Usuário (Offboarding Imediato)**:
   - Clique em **Desativar**. O usuário perde imediatamente a permissão de login.
   - Sua sessão ativa é imediatamente invalidada no banco SQLite (`sessions`).
   - A ação é auditada com o carimbo do administrador autor.

---

## 6. Procedimento de Falha do Host (Disaster Recovery Runbook)

### 6.1. Cenário
O computador/notebook dedicado que hospeda o CRM sofreu falha grave de hardware (queima de placa, quebra de disco, roubo ou dano físico irreparável).

**Objetivos de Recuperação:**
- **RTO (Recovery Time Objective):** < 30 minutos para restabelecer a operação.
- **RPO (Recovery Point Objective):** Perda máxima de dados restrita ao último backup diário gerado (ou menos, se houver cópia em pendrive no dia).

### 6.2. Passo a Passo de Recuperação em Nova Máquina

1. **Providenciar nova máquina física ou notebook**:
   - Sistema Operacional: Windows 10/11 ou Linux.
   - Pré-requisitos instalados: Docker Desktop (ou Docker Engine + Docker Compose) e Git (ou descompactador de arquivos).

2. **Obter o código da aplicação**:
   ```bash
   git clone <URL_DO_REPOSITORIO> C:\Docker\plataforma_pinciara
   cd C:\Docker\plataforma_pinciara
   ```

3. **Restabelecer o arquivo de configuração `.env`**:
   - Copie o arquivo `.env` salvo na custódia segura ou crie a partir do modelo:
     ```bash
     cp .env.example .env
     ```
   - **IMPORTANTE:** Configure a chave `APP_SECRET` e o fuso horário `APP_TIMEZONE=America/Sao_Paulo`.

4. **Obter o arquivo de backup mais recente**:
   - Localize o último backup disponível no pendrive, cópia externa ou nuvem (`backup_YYYY-MM-DD_HHmmss.sqlite`).
   - Copie o arquivo para a pasta `storage/backups/`.

5. **Restaurar o banco de dados**:
   ```bash
   php bin/restore.php storage/backups/backup_YYYY-MM-DD_HHmmss.sqlite --force
   ```
   *Nota: Se o PHP não estiver instalado no host, você pode subir o contêiner e executar a restauração de dentro dele (Passo 6 e 7).*

6. **Inicializar a aplicação via Docker Compose**:
   ```bash
   docker compose up -d --build
   ```

7. **Validação do Ambiente Restaurado**:
   ```bash
   # Executa o diagnóstico completo de saúde
   docker compose exec app php bin/health-check.php
   ```
   Acesse no navegador: `http://localhost:8080/health`.

8. **Comunicação à Equipe**:
   - Avise os corretores de que o CRM está novamente online no endereço IP local da nova máquina.

---

## 7. Monitoramento e Diagnóstico Contínuo

O sistema conta com o utilitário de diagnóstico `bin/health-check.php`, que realiza a verificação de 6 pilares vitais:
1. **Conexão SQLite e Latência**.
2. **Integridade do Banco** (`PRAGMA integrity_check` e `PRAGMA foreign_key_check`).
3. **Modo WAL ativo**.
4. **Espaço em Disco** (alerta se espaço livre for inferior a 1 GB ou uso superior a 90%).
5. **Frescor dos Backups** (alerta se o último backup tiver mais de 24 horas).
6. **Permissões de Escrita** e ausência de erros recentes nos logs.

### Execução de Diagnóstico:
```bash
php bin/health-check.php
```
Ou em formato JSON para integração com ferramentas de monitoramento:
```bash
php bin/health-check.php --json
```
