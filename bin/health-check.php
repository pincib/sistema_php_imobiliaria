<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Backup\BackupService;
use App\Database\Connection;
use Dotenv\Dotenv;

// Carrega variáveis de ambiente se existir o arquivo .env
$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$options = getopt('', [
    'json',
    'quiet',
    'warn-backup-hours::',
    'help',
]);

if (isset($options['help'])) {
    echo "========================================================\n";
    echo "   Plataforma Pinciara — Diagnóstico e Monitoramento    \n";
    echo "========================================================\n\n";
    echo "Uso:\n";
    echo "  php bin/health-check.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --json                       Retorna status em formato JSON estruturado\n";
    echo "  --quiet                      Suprime saída padrão, retornando apenas código de saída (0 ou 1)\n";
    echo "  --warn-backup-hours=<horas>  Limite de tolerância de horas desde o último backup (padrão: 24)\n";
    echo "  --help                       Exibe esta ajuda\n";
    exit(0);
}

$isJson = isset($options['json']);
$isQuiet = isset($options['quiet']);
$warnBackupHours = isset($options['warn-backup-hours']) ? (float)$options['warn-backup-hours'] : 24.0;

$checks = [];
$hasErrors = false;
$hasWarnings = false;

// 1. Disponibilidade e Conexão com SQLite
$dbStart = microtime(true);
try {
    $pdo = Connection::getPdo();
    $pdo->query('SELECT 1')->fetch();
    $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);

    $checks['database_availability'] = [
        'status' => 'ok',
        'latency_ms' => $dbDuration,
        'message' => "Conexão ativa ({$dbDuration} ms)",
    ];
} catch (Throwable $e) {
    $hasErrors = true;
    $checks['database_availability'] = [
        'status' => 'critical',
        'message' => 'Falha de conexão com SQLite: ' . $e->getMessage(),
    ];
}

// 2. Integridade do Banco e Configurações WAL
if ($checks['database_availability']['status'] === 'ok') {
    try {
        $integrity = (string)$pdo->query('PRAGMA integrity_check;')->fetchColumn();
        $fkErrors = $pdo->query('PRAGMA foreign_key_check;')->fetchAll();
        $journalMode = (string)$pdo->query('PRAGMA journal_mode;')->fetchColumn();

        $dbResolvedPath = $_ENV['DB_PATH'] ?? 'storage/database.sqlite';
        if (!str_starts_with($dbResolvedPath, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dbResolvedPath)) {
            $dbResolvedPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbResolvedPath);
        }

        $dbSize = file_exists($dbResolvedPath) ? (int)filesize($dbResolvedPath) : 0;
        $walFile = $dbResolvedPath . '-wal';
        $walSize = file_exists($walFile) ? (int)filesize($walFile) : 0;

        $dbOk = ($integrity === 'ok' && empty($fkErrors));
        if (!$dbOk) {
            $hasErrors = true;
        }

        $checks['database_integrity'] = [
            'status' => $dbOk ? 'ok' : 'critical',
            'integrity_check' => $integrity,
            'foreign_key_violations' => count($fkErrors),
            'journal_mode' => $journalMode,
            'size_bytes' => $dbSize,
            'size_formatted' => BackupService::formatBytes($dbSize),
            'wal_size_formatted' => BackupService::formatBytes($walSize),
            'message' => $dbOk ? "Banco íntegro (Modo: {$journalMode}, Tamanho: " . BackupService::formatBytes($dbSize) . ")" : "Falha na verificação de integridade ({$integrity})",
        ];
    } catch (Throwable $e) {
        $hasErrors = true;
        $checks['database_integrity'] = [
            'status' => 'critical',
            'message' => 'Erro ao verificar integridade: ' . $e->getMessage(),
        ];
    }
}

// 3. Espaço em Disco (Storage)
$storageDir = $root . DIRECTORY_SEPARATOR . 'storage';
$freeBytes = @disk_free_space($storageDir);
$totalBytes = @disk_total_space($storageDir);

if ($freeBytes !== false && $totalBytes !== false && $totalBytes > 0) {
    $usedBytes = $totalBytes - $freeBytes;
    $usedPercent = round(($usedBytes / $totalBytes) * 100, 1);
    $freeGb = round($freeBytes / (1024 * 1024 * 1024), 2);

    $diskStatus = 'ok';
    if ($freeGb < 0.25 || $usedPercent > 95) {
        $diskStatus = 'critical';
        $hasErrors = true;
    } elseif ($freeGb < 1.0 || $usedPercent > 90) {
        $diskStatus = 'warning';
        $hasWarnings = true;
    }

    $checks['disk_space'] = [
        'status' => $diskStatus,
        'free_formatted' => BackupService::formatBytes((int)$freeBytes),
        'total_formatted' => BackupService::formatBytes((int)$totalBytes),
        'used_percent' => $usedPercent,
        'message' => "{$usedPercent}% em uso ({$freeGb} GB livres de " . BackupService::formatBytes((int)$totalBytes) . ")",
    ];
} else {
    $checks['disk_space'] = [
        'status' => 'warning',
        'message' => 'Não foi possível ler as métricas da partição de disco.',
    ];
}

// 4. Status de Backups e Recência
$backupService = new BackupService();
$latestBackup = $backupService->getLatestBackup();

if ($latestBackup) {
    $ageHours = $latestBackup['age_hours'];
    $backupStatus = 'ok';

    if ($ageHours > ($warnBackupHours * 2)) {
        $backupStatus = 'critical';
        $hasErrors = true;
    } elseif ($ageHours > $warnBackupHours) {
        $backupStatus = 'warning';
        $hasWarnings = true;
    }

    $checks['backup_status'] = [
        'status' => $backupStatus,
        'latest_backup' => $latestBackup['filename'],
        'created_at' => $latestBackup['created_at'],
        'age_hours' => $ageHours,
        'size_formatted' => $latestBackup['size_formatted'],
        'message' => ($backupStatus === 'ok')
            ? "Backup recente detectado ({$latestBackup['filename']}, há {$ageHours}h)"
            : "Alerta: último backup tem {$ageHours} horas (tolerância: {$warnBackupHours}h)",
    ];
} else {
    $hasErrors = true;
    $checks['backup_status'] = [
        'status' => 'critical',
        'message' => 'Nenhum backup encontrado no diretório de backups!',
    ];
}

// 5. Permissões de Escrita
$writableDirs = [
    'storage' => $storageDir,
    'backups' => $storageDir . DIRECTORY_SEPARATOR . 'backups',
    'logs' => $storageDir . DIRECTORY_SEPARATOR . 'logs',
];

$permIssues = [];
foreach ($writableDirs as $name => $path) {
    if (!is_writable($path)) {
        $permIssues[] = $name;
    }
}

if (!empty($permIssues)) {
    $hasErrors = true;
    $checks['permissions'] = [
        'status' => 'critical',
        'message' => 'Diretórios sem permissão de escrita: ' . implode(', ', $permIssues),
    ];
} else {
    $checks['permissions'] = [
        'status' => 'ok',
        'message' => 'Diretórios de storage, backups e logs possuem permissão de escrita.',
    ];
}

// 6. Integridade dos Logs
$backupLogPath = $storageDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'backup.log';
$recentLogErrors = 0;
if (file_exists($backupLogPath)) {
    $lines = file($backupLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $recentLines = array_slice($lines, -30);
    foreach ($recentLines as $line) {
        if (str_contains($line, '[ERROR]') || str_contains($line, '[CRITICAL]')) {
            $recentLogErrors++;
        }
    }
}

$checks['logs_health'] = [
    'status' => $recentLogErrors === 0 ? 'ok' : 'warning',
    'recent_critical_errors' => $recentLogErrors,
    'backup_log_size' => file_exists($backupLogPath) ? BackupService::formatBytes((int)filesize($backupLogPath)) : '0 B',
    'message' => ($recentLogErrors === 0)
        ? 'Nenhum erro recente registrado nos logs de backup.'
        : "{$recentLogErrors} erro(s) ou falha(s) recente(s) identificada(s) nos logs.",
];
if ($recentLogErrors > 0) {
    $hasWarnings = true;
}

$globalStatus = $hasErrors ? 'CRITICAL' : ($hasWarnings ? 'WARNING' : 'OK');

$outputPayload = [
    'system' => 'Plataforma Pinciara CRM',
    'status' => $globalStatus,
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => $checks,
];

if ($isJson) {
    echo json_encode($outputPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit($hasErrors ? 1 : 0);
}

if ($isQuiet) {
    exit($hasErrors ? 1 : 0);
}

// Saída amigável no terminal
echo "========================================================\n";
echo "   Plataforma Pinciara — Diagnóstico do Sistema         \n";
echo "========================================================\n";
echo " Status Geral: [{$globalStatus}]\n";
echo " Data/Hora:    {$outputPayload['timestamp']}\n";
echo "--------------------------------------------------------\n";

foreach ($checks as $name => $data) {
    $badge = strtoupper($data['status']);
    printf(" [%-8s] %-24s: %s\n", $badge, $name, $data['message']);
}

echo "========================================================\n";

exit($hasErrors ? 1 : 0);
