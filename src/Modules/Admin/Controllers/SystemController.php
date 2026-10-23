<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Audit\AuditService;
use App\Auth\Session\SessionManager;
use App\Backup\BackupService;
use App\Database\Connection;
use App\View\Renderer;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class SystemController
{
    private Renderer $renderer;
    private BackupService $backupService;

    public function __construct(?Renderer $renderer = null, ?BackupService $backupService = null)
    {
        $this->renderer = $renderer ?? new Renderer();
        $this->backupService = $backupService ?? new BackupService();
    }

    public function index(Request $request, Response $response): Response
    {
        $root = dirname(__DIR__, 4);
        $storageDir = $root . DIRECTORY_SEPARATOR . 'storage';

        // 1. Integridade do SQLite
        $pdo = Connection::getPdo();
        $integrity = (string)$pdo->query('PRAGMA integrity_check;')->fetchColumn();
        $fkErrors = $pdo->query('PRAGMA foreign_key_check;')->fetchAll();
        $journalMode = (string)$pdo->query('PRAGMA journal_mode;')->fetchColumn();

        $dbResolvedPath = $_ENV['DB_PATH'] ?? 'storage/database.sqlite';
        if (!str_starts_with($dbResolvedPath, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dbResolvedPath)) {
            $dbResolvedPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbResolvedPath);
        }
        $dbSize = file_exists($dbResolvedPath) ? (int)filesize($dbResolvedPath) : 0;
        $walSize = file_exists($dbResolvedPath . '-wal') ? (int)filesize($dbResolvedPath . '-wal') : 0;

        // 2. Espaço em Disco
        $freeBytes = @disk_free_space($storageDir) ?: 0;
        $totalBytes = @disk_total_space($storageDir) ?: 1;
        $usedBytes = $totalBytes - $freeBytes;
        $usedPercent = round(($usedBytes / $totalBytes) * 100, 1);

        // 3. Backups e Logs
        $backups = $this->backupService->listBackups(includeSnapshots: true);
        $latestBackup = $this->backupService->getLatestBackup();

        $backupLogPath = $this->backupService->getLogPath();
        $recentLogs = [];
        if (file_exists($backupLogPath)) {
            $allLines = file($backupLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $recentLogs = array_reverse(array_slice($allLines, -20));
        }

        $html = $this->renderer->render('admin/system', [
            'title' => 'Diagnóstico, Monitoramento e Backups',
            'system' => [
                'php_version' => PHP_VERSION,
                'sqlite_version' => $pdo->query('SELECT sqlite_version();')->fetchColumn(),
                'journal_mode' => $journalMode,
                'integrity' => $integrity,
                'fk_violations' => count($fkErrors),
                'db_size' => BackupService::formatBytes($dbSize),
                'wal_size' => BackupService::formatBytes($walSize),
                'free_disk' => BackupService::formatBytes((int)$freeBytes),
                'total_disk' => BackupService::formatBytes((int)$totalBytes),
                'used_disk_percent' => $usedPercent,
                'storage_writable' => is_writable($storageDir),
            ],
            'backups' => $backups,
            'latestBackup' => $latestBackup,
            'recentLogs' => $recentLogs,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function triggerBackup(Request $request, Response $response): Response
    {
        try {
            $result = $this->backupService->createBackup();
            SessionManager::flash('success', "Backup consistente gerado com sucesso: {$result['filename']} ({$result['size_formatted']}). Integridade validada.");
        } catch (Throwable $e) {
            SessionManager::flash('error', "Falha ao gerar backup: " . $e->getMessage());
        }

        return $response->withHeader('Location', '/admin/system')->withStatus(302);
    }

    public function triggerTestRestore(Request $request, Response $response): Response
    {
        try {
            $latest = $this->backupService->getLatestBackup();
            if (!$latest) {
                // Cria um backup primeiro se necessário
                $fresh = $this->backupService->createBackup(autoRotate: false);
                $targetFile = $fresh['path'];
            } else {
                $targetFile = $latest['path'];
            }

            // Simula restauração em ambiente isolado
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'drill_web_' . uniqid();
            @mkdir($tempDir, 0750, true);
            $tempDb = $tempDir . DIRECTORY_SEPARATOR . 'test.sqlite';

            copy($targetFile, $tempDb);

            $pdo = new PDO("sqlite:{$tempDb}");
            $integrity = $pdo->query('PRAGMA integrity_check;')->fetchColumn();
            $quick = $pdo->query('PRAGMA quick_check;')->fetchColumn();
            $fkErrors = $pdo->query('PRAGMA foreign_key_check;')->fetchAll();

            unset($pdo);
            @unlink($tempDb);
            @rmdir($tempDir);

            if ($integrity === 'ok' && $quick === 'ok' && empty($fkErrors)) {
                AuditService::log('restore_drill.web', 'backup', basename($targetFile), null, ['status' => 'PASSED']);
                SessionManager::flash('success', "Simulação de restauração realizada com sucesso! O backup " . basename($targetFile) . " passou em 100% dos testes de integridade e chaves estrangeiras.");
            } else {
                SessionManager::flash('error', "Simulação de restauração detectou inconsistências no backup.");
            }
        } catch (Throwable $e) {
            SessionManager::flash('error', "Erro ao simular restauração: " . $e->getMessage());
        }

        return $response->withHeader('Location', '/admin/system')->withStatus(302);
    }
}
