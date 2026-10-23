<?php

declare(strict_types=1);

namespace App\Backup;

use App\Audit\AuditService;
use App\Database\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class BackupService
{
    private string $dbPath;
    private string $backupDir;
    private string $logPath;
    private ?string $externalDir;
    private int $defaultKeepCount;
    private int $defaultRetentionDays;

    public function __construct(
        ?string $dbPath = null,
        ?string $backupDir = null,
        ?string $externalDir = null,
        ?string $logPath = null,
        int $defaultKeepCount = 14,
        int $defaultRetentionDays = 30
    ) {
        $baseDir = dirname(__DIR__, 2);

        // Resolve caminho do banco ativo
        $resolvedDb = $dbPath ?? $_ENV['DB_PATH'] ?? getenv('DB_PATH') ?: 'storage/database.sqlite';
        if ($resolvedDb !== ':memory:' && !str_starts_with($resolvedDb, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $resolvedDb)) {
            $resolvedDb = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedDb);
        }
        $this->dbPath = $resolvedDb;

        // Resolve diretório de backups
        $resolvedBackupDir = $backupDir ?? $_ENV['BACKUP_DIR'] ?? getenv('BACKUP_DIR') ?: 'storage/backups';
        if (!str_starts_with($resolvedBackupDir, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $resolvedBackupDir)) {
            $resolvedBackupDir = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedBackupDir);
        }
        $this->backupDir = rtrim($resolvedBackupDir, '\\/');

        // Resolve cópia externa fora do host
        $resolvedExtDir = $externalDir ?? $_ENV['BACKUP_EXTERNAL_DIR'] ?? getenv('BACKUP_EXTERNAL_DIR') ?: null;
        if (!empty($resolvedExtDir) && !str_starts_with($resolvedExtDir, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $resolvedExtDir)) {
            $resolvedExtDir = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedExtDir);
        }
        $this->externalDir = !empty($resolvedExtDir) ? rtrim($resolvedExtDir, '\\/') : null;

        // Resolve log de backup
        $resolvedLog = $logPath ?? $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'backup.log';
        $this->logPath = $resolvedLog;

        $this->defaultKeepCount = max(3, (int)($_ENV['BACKUP_KEEP_COUNT'] ?? $defaultKeepCount));
        $this->defaultRetentionDays = max(1, (int)($_ENV['BACKUP_RETENTION_DAYS'] ?? $defaultRetentionDays));

        $this->ensureDirectoriesProtected();
    }

    /**
     * Garante que os diretórios de backup e logs existam e estejam protegidos por .htaccess.
     */
    private function ensureDirectoriesProtected(): void
    {
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0750, true);
        }

        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }

        // Defesa em profundidade: .htaccess no diretório de backups
        $htaccessPath = $this->backupDir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            @file_put_contents(
                $htaccessPath,
                "# Apache 2.4+\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n# Apache 2.2 fallback\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
            );
        }
    }

    /**
     * Executa backup consistente do banco SQLite ativo utilizando VACUUM INTO.
     *
     * @param string|null $customFilename Nome ou caminho customizado opcional
     * @param bool $autoRotate Se verdadeiro, aplica rotação de retenção após o backup
     * @return array{
     *     status: string,
     *     filename: string,
     *     path: string,
     *     size_bytes: int,
     *     size_formatted: string,
     *     sha256: string,
     *     duration_seconds: float,
     *     integrity: string,
     *     rotated_files: list<string>,
     *     external_copy: array{status: string, destination?: string, error?: string},
     *     created_at: string
     * }
     */
    public function createBackup(?string $customFilename = null, bool $autoRotate = true): array
    {
        $startTime = microtime(true);

        if (!file_exists($this->dbPath)) {
            $msg = "Banco de dados principal não encontrado para backup: {$this->dbPath}";
            $this->log('ERROR', $msg);
            throw new RuntimeException($msg);
        }

        $timestamp = date('Y-m-d_His');
        $filename = $customFilename ? basename($customFilename) : "backup_{$timestamp}.sqlite";
        $targetPath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        // Se o arquivo de destino já existir, remove antes
        if (file_exists($targetPath)) {
            @unlink($targetPath);
        }

        // SQLite exige caminho com barras normais em literais SQL
        $cleanTargetSqlPath = str_replace('\\', '/', $targetPath);

        try {
            $pdo = Connection::getPdo();

            // Executa VACUUM INTO atomicamente e com integridade transacional
            $quotedPath = $pdo->quote($cleanTargetSqlPath);
            $pdo->exec("VACUUM INTO {$quotedPath}");

            if (!file_exists($targetPath) || filesize($targetPath) === 0) {
                throw new RuntimeException("Arquivo de backup não foi gerado ou está vazio: {$targetPath}");
            }

            // Verificação imediata de integridade no arquivo gerado
            $integrityCheck = $this->checkBackupIntegrity($targetPath);
            if ($integrityCheck !== 'ok') {
                @unlink($targetPath);
                throw new RuntimeException("Falha na verificação de integridade do backup gerado: {$integrityCheck}");
            }

            $sizeBytes = (int)filesize($targetPath);
            $sha256 = (string)hash_file('sha256', $targetPath);
            $duration = round(microtime(true) - $startTime, 4);

            // Rotação de backups antigos conforme política de retenção
            $rotated = [];
            if ($autoRotate) {
                $rotated = $this->rotateBackups($this->defaultKeepCount, $this->defaultRetentionDays);
            }

            // Cópia fora do host se houver diretório externo configurado
            $externalResult = ['status' => 'skipped'];
            if (!empty($this->externalDir)) {
                $externalResult = $this->copyOffHost($targetPath, $this->externalDir);
            }

            $result = [
                'status' => 'success',
                'filename' => $filename,
                'path' => $targetPath,
                'size_bytes' => $sizeBytes,
                'size_formatted' => $this->formatBytes($sizeBytes),
                'sha256' => $sha256,
                'duration_seconds' => $duration,
                'integrity' => 'ok',
                'rotated_files' => $rotated,
                'external_copy' => $externalResult,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $this->log('SUCCESS', "Backup concluído com sucesso: {$filename} ({$result['size_formatted']}, {$duration}s, SHA256: " . substr($sha256, 0, 12) . "...)");

            AuditService::log(
                action: 'backup.create',
                entityType: 'backup',
                entityId: $filename,
                newValues: [
                    'size_bytes' => $sizeBytes,
                    'sha256' => $sha256,
                    'duration_seconds' => $duration,
                    'external_copy_status' => $externalResult['status'],
                ]
            );

            return $result;
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 4);
            $errorMsg = "Falha ao gerar backup: " . $e->getMessage();
            $this->log('ERROR', "{$errorMsg} ({$duration}s)");

            AuditService::log(
                action: 'backup.failure',
                entityType: 'backup',
                entityId: $filename,
                newValues: ['error' => $e->getMessage()]
            );

            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }

            throw new RuntimeException($errorMsg, 0, $e);
        }
    }

    /**
     * Executa rotação e expurgo de backups antigos, respeitando retenção mínima e por dias.
     *
     * @return list<string> Lista de nomes de arquivos removidos
     */
    public function rotateBackups(?int $keepCount = null, ?int $retentionDays = null): array
    {
        $keep = max(3, $keepCount ?? $this->defaultKeepCount);
        $days = max(1, $retentionDays ?? $this->defaultRetentionDays);
        $thresholdTimestamp = time() - ($days * 86400);

        $backups = $this->listBackups(includeSnapshots: false);
        $removed = [];

        // Mantém sempre no mínimo $keep arquivos mais recentes
        if (count($backups) <= $keep) {
            return $removed;
        }

        // Ordenados do mais recente para o mais antigo; os primeiros $keep são protegidos
        $candidates = array_slice($backups, $keep);

        foreach ($candidates as $item) {
            if ($item['mtime'] < $thresholdTimestamp) {
                if (@unlink($item['path'])) {
                    $removed[] = $item['filename'];
                    $this->log('INFO', "Backup antigo removido por retenção ({$days}d): {$item['filename']}");
                }
            }
        }

        return $removed;
    }

    /**
     * Copia o arquivo de backup para um destino fora do host (drive montado, pendrive ou compartilhamento).
     *
     * @return array{status: string, destination?: string, error?: string}
     */
    public function copyOffHost(string $sourcePath, string $targetDir): array
    {
        try {
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
                throw new RuntimeException("Diretório de destino externo inacessível: {$targetDir}");
            }

            $filename = basename($sourcePath);
            $destination = rtrim($targetDir, '\\/') . DIRECTORY_SEPARATOR . $filename;

            if (!@copy($sourcePath, $destination)) {
                throw new RuntimeException("Falha ao copiar arquivo para: {$destination}");
            }

            $sourceHash = hash_file('sha256', $sourcePath);
            $destHash = hash_file('sha256', $destination);

            if ($sourceHash !== $destHash) {
                @unlink($destination);
                throw new RuntimeException("Checksum do arquivo copiado não confere com a origem!");
            }

            $this->log('SUCCESS', "Cópia fora do host concluída: {$destination}");

            return [
                'status' => 'success',
                'destination' => $destination,
            ];
        } catch (Throwable $e) {
            $msg = "Aviso: falha na cópia fora do host: " . $e->getMessage();
            $this->log('WARNING', $msg);
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Valida integridade e estrutura de um arquivo de backup SQLite.
     *
     * @return array{
     *     valid: bool,
     *     integrity: string,
     *     quick_check: string,
     *     foreign_keys: string,
     *     tables_count: int,
     *     users_count: int,
     *     leads_count: int,
     *     missing_tables: list<string>,
     *     error?: string
     * }
     */
    public function verifyBackup(string $backupPath): array
    {
        if (!file_exists($backupPath)) {
            return [
                'valid' => false,
                'integrity' => 'error',
                'quick_check' => 'error',
                'foreign_keys' => 'error',
                'tables_count' => 0,
                'users_count' => 0,
                'leads_count' => 0,
                'missing_tables' => [],
                'error' => "Arquivo não encontrado: {$backupPath}",
            ];
        }

        try {
            $dsn = "sqlite:{$backupPath}";
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $pdo->exec('PRAGMA busy_timeout = 3000;');

            // 1. Integridade completa
            $integrityStmt = $pdo->query('PRAGMA integrity_check;');
            $integrityRow = $integrityStmt->fetchColumn();
            $integrity = ($integrityRow === 'ok') ? 'ok' : (string)$integrityRow;

            // 2. Quick check
            $quickStmt = $pdo->query('PRAGMA quick_check;');
            $quickRow = $quickStmt->fetchColumn();
            $quickCheck = ($quickRow === 'ok') ? 'ok' : (string)$quickRow;

            // 3. Foreign key check
            $fkStmt = $pdo->query('PRAGMA foreign_key_check;');
            $fkErrors = $fkStmt->fetchAll();
            $fkCheck = empty($fkErrors) ? 'ok' : 'violations: ' . count($fkErrors);

            // 4. Verificação de tabelas essenciais
            $tablesStmt = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
            $tablesCount = (int)$tablesStmt->fetchColumn();

            // Keep restore preflight aligned with the current migrated schema and restore drill.
            $requiredTables = [
                'roles', 'users', 'sessions', 'audit_logs', 'leads',
                'properties', 'lead_properties', 'lead_interactions',
                'lead_assignments', 'lead_follow_ups', 'import_batches',
                'signboards', 'signboard_movements', 'owners',
                'owner_properties', 'whatsapp_contact_logs', 'import_source_rows',
            ];
            $presentTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';")
                ->fetchAll(PDO::FETCH_COLUMN);
            $missingTables = array_values(array_diff($requiredTables, $presentTables));

            // 5. Contagem de usuários e leads
            $usersCount = 0;
            $hasUsers = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='users';")->fetch();
            if ($hasUsers) {
                $usersCount = (int)$pdo->query('SELECT COUNT(*) FROM users;')->fetchColumn();
            }

            $leadsCount = 0;
            $hasLeads = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='leads';")->fetch();
            if ($hasLeads) {
                $leadsCount = (int)$pdo->query('SELECT COUNT(*) FROM leads;')->fetchColumn();
            }

            $isValid = ($integrity === 'ok' && $quickCheck === 'ok' && $fkCheck === 'ok' && $missingTables === []);

            return [
                'valid' => $isValid,
                'integrity' => $integrity,
                'quick_check' => $quickCheck,
                'foreign_keys' => $fkCheck,
                'tables_count' => $tablesCount,
                'users_count' => $usersCount,
                'leads_count' => $leadsCount,
                'missing_tables' => $missingTables,
                ...($missingTables !== [] ? ['error' => 'Tabelas essenciais ausentes: ' . implode(', ', $missingTables)] : []),
            ];
        } catch (Throwable $e) {
            return [
                'valid' => false,
                'integrity' => 'error',
                'quick_check' => 'error',
                'foreign_keys' => 'error',
                'tables_count' => 0,
                'users_count' => 0,
                'leads_count' => 0,
                'missing_tables' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restaura o banco de dados principal a partir de um arquivo de backup consistente.
     *
     * @param string $backupPath Caminho para o arquivo .sqlite do backup
     * @param bool $createSafetySnapshot Se verdadeiro, cria snapshot do banco atual antes de sobrescrever
     * @return array{
     *     status: string,
     *     restored_file: string,
     *     safety_snapshot?: string,
     *     tables_restored: int,
     *     users_count: int,
     *     leads_count: int,
     *     restored_at: string
     * }
     */
    public function restoreBackup(string $backupPath, bool $createSafetySnapshot = true): array
    {
        $startTime = microtime(true);

        if (!file_exists($backupPath)) {
            $msg = "Arquivo de backup para restauração não existe: {$backupPath}";
            $this->log('ERROR', $msg);
            throw new RuntimeException($msg);
        }

        // Validação prévia do arquivo de backup
        $verification = $this->verifyBackup($backupPath);
        if (!$verification['valid']) {
            $errorDetail = $verification['error'] ?? "Integridade: {$verification['integrity']}, FK: {$verification['foreign_keys']}";
            $msg = "O arquivo de backup é inválido ou está corrompido: {$errorDetail}";
            $this->log('ERROR', "Restauração abortada: {$msg}");
            throw new RuntimeException($msg);
        }

        $safetySnapshotPath = null;

        // Snapshot de segurança do banco ativo atual
        if ($createSafetySnapshot && file_exists($this->dbPath)) {
            $snapTimestamp = date('Y-m-d_His');
            $snapName = "pre_restore_{$snapTimestamp}.sqlite";
            $safetySnapshotPath = $this->backupDir . DIRECTORY_SEPARATOR . $snapName;

            try {
                // Tenta criar via VACUUM INTO se o banco atual estiver acessível
                $pdoCurrent = Connection::getPdo();
                $cleanSnapSql = str_replace('\\', '/', $safetySnapshotPath);
                $pdoCurrent->exec("VACUUM INTO " . $pdoCurrent->quote($cleanSnapSql));
            } catch (Throwable $e) {
                @unlink($safetySnapshotPath);
                throw new RuntimeException('Não foi possível criar o snapshot de segurança; a restauração foi cancelada.', 0, $e);
            }
            $pdoCurrent = null;

            $snapshotVerification = $this->verifyBackup($safetySnapshotPath);
            if (!$snapshotVerification['valid']) {
                @unlink($safetySnapshotPath);
                $detail = $snapshotVerification['error'] ?? 'integridade ou chaves estrangeiras inválidas';
                throw new RuntimeException("O snapshot de segurança não passou na validação ({$detail}); a restauração foi cancelada.");
            }

            $this->log('INFO', "Snapshot de segurança pré-restauração criado: {$snapName}");
        }

        // Fecha conexões ativas com o banco principal para liberar locks de arquivo
        Connection::close();
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        Connection::setPath($this->dbPath);

        // Limpa arquivos de diário e memória compartilhada WAL para evitar conflito com o novo banco
        $walPath = $this->dbPath . '-wal';
        $shmPath = $this->dbPath . '-shm';

        if (file_exists($walPath)) {
            @file_put_contents($walPath, '');
            @unlink($walPath);
        }
        if (file_exists($shmPath)) {
            @file_put_contents($shmPath, '');
            @unlink($shmPath);
        }

        // Substituição do arquivo do banco ativo
        if (!@copy($backupPath, $this->dbPath)) {
            $msg = "Falha crítica ao copiar arquivo restaurado para o banco principal ({$this->dbPath})";
            $this->log('CRITICAL', $msg);
            throw new RuntimeException($msg);
        }

        // Reabre conexão e valida o banco recém-restaurado
        try {
            $newPdo = Connection::getPdo();
            $newPdo->exec('PRAGMA foreign_keys = ON;');
            $newPdo->exec('PRAGMA busy_timeout = 5000;');

            $postCheck = $newPdo->query('PRAGMA quick_check;')->fetchColumn();
            if ($postCheck !== 'ok') {
                throw new RuntimeException("Banco restaurado reportou erro no quick_check: {$postCheck}");
            }

            $usersCount = (int)$newPdo->query('SELECT COUNT(*) FROM users;')->fetchColumn();
            $leadsCount = 0;
            $hasLeads = $newPdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='leads';")->fetch();
            if ($hasLeads) {
                $leadsCount = (int)$newPdo->query('SELECT COUNT(*) FROM leads;')->fetchColumn();
            }

            $duration = round(microtime(true) - $startTime, 4);
            $filename = basename($backupPath);

            $this->log('SUCCESS', "Restauração concluída com sucesso: {$filename} (Usuários: {$usersCount}, Leads: {$leadsCount}, {$duration}s)");

            AuditService::log(
                action: 'backup.restore',
                entityType: 'backup',
                entityId: $filename,
                newValues: [
                    'source_path' => $backupPath,
                    'safety_snapshot' => $safetySnapshotPath ? basename($safetySnapshotPath) : null,
                    'users_count' => $usersCount,
                    'leads_count' => $leadsCount,
                    'duration_seconds' => $duration,
                ]
            );

            return [
                'status' => 'success',
                'restored_file' => $filename,
                'safety_snapshot' => $safetySnapshotPath ? basename($safetySnapshotPath) : null,
                'tables_restored' => $verification['tables_count'],
                'users_count' => $usersCount,
                'leads_count' => $leadsCount,
                'restored_at' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            $msg = "Erro na pós-validação do banco restaurado: " . $e->getMessage();
            $this->log('CRITICAL', $msg);
            throw new RuntimeException($msg, 0, $e);
        }
    }

    /**
     * Retorna a lista de backups disponíveis ordenados do mais recente para o mais antigo.
     *
     * @return list<array{
     *     filename: string,
     *     path: string,
     *     size_bytes: int,
     *     size_formatted: string,
     *     created_at: string,
     *     mtime: int,
     *     age_hours: float,
     *     is_snapshot: bool
     * }>
     */
    public function listBackups(bool $includeSnapshots = true): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }

        $files = scandir($this->backupDir, SCANDIR_SORT_DESCENDING) ?: [];
        $result = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.sqlite')) {
                continue;
            }

            $isSnapshot = str_starts_with($file, 'pre_restore_');
            if (!$includeSnapshots && $isSnapshot) {
                continue;
            }

            $path = $this->backupDir . DIRECTORY_SEPARATOR . $file;
            $size = (int)@filesize($path);
            $mtime = (int)@filemtime($path);
            $ageHours = round((time() - $mtime) / 3600, 1);

            $result[] = [
                'filename' => $file,
                'path' => $path,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'mtime' => $mtime,
                'age_hours' => $ageHours,
                'is_snapshot' => $isSnapshot,
            ];
        }

        // Ordenação garantida por mtime decrescente
        usort($result, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $result;
    }

    /**
     * Retorna metadados do backup mais recente (excluindo snapshots de segurança pré-restauração).
     *
     * @return array{
     *     filename: string,
     *     path: string,
     *     size_bytes: int,
     *     size_formatted: string,
     *     created_at: string,
     *     age_hours: float
     * }|null
     */
    public function getLatestBackup(): ?array
    {
        $list = $this->listBackups(includeSnapshots: false);
        return $list[0] ?? null;
    }

    /**
     * Executa checagem de integridade rápida de um arquivo SQLite via PRAGMA integrity_check.
     */
    private function checkBackupIntegrity(string $path): string
    {
        try {
            $pdo = new PDO("sqlite:{$path}");
            $stmt = $pdo->query('PRAGMA integrity_check;');
            $result = (string)$stmt->fetchColumn();
            return ($result === 'ok') ? 'ok' : $result;
        } catch (Throwable $e) {
            return 'exception: ' . $e->getMessage();
        }
    }

    /**
     * Registra mensagens no log dedicado de backups com timestamp e nível.
     */
    private function log(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}\n";
        @file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Formata tamanho de bytes em formato legível (KB, MB, GB).
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1073741824, 2) . ' GB';
    }

    public function getBackupDir(): string
    {
        return $this->backupDir;
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }
}
