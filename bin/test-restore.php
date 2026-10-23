<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Backup\BackupService;
use Dotenv\Dotenv;

// Carrega variáveis de ambiente se existir o arquivo .env
$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$options = getopt('', [
    'file::',
    'create-backup-first',
    'json',
    'help',
]);

if (isset($options['help'])) {
    echo "========================================================\n";
    echo "   Plataforma Pinciara — Teste Mensal de Restauração   \n";
    echo "========================================================\n\n";
    echo "Uso:\n";
    echo "  php bin/test-restore.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --file=<caminho>             Caminho do arquivo de backup a ser testado\n";
    echo "  --create-backup-first        Gera um backup fresco antes de iniciar o teste de restauração\n";
    echo "  --json                       Retorna o laudo da simulação em formato JSON\n";
    echo "  --help                       Exibe esta ajuda\n";
    exit(0);
}

$isJson = isset($options['json']);
$service = new BackupService();

try {
    if (isset($options['create-backup-first'])) {
        $fresh = $service->createBackup(autoRotate: false);
        $backupPath = $fresh['path'];
    } else {
        $backupPath = $options['file'] ?? null;
        if (empty($backupPath)) {
            $latest = $service->getLatestBackup();
            if (!$latest) {
                // Se não há nenhum backup, cria um primeiro para viabilizar o teste
                $fresh = $service->createBackup(autoRotate: false);
                $backupPath = $fresh['path'];
            } else {
                $backupPath = $latest['path'];
            }
        }
    }

    if (!file_exists($backupPath)) {
        throw new RuntimeException("Arquivo de backup alvo não encontrado: {$backupPath}");
    }

    // 1. Cria diretório temporário isolado para simulação do ambiente separado
    $drillId = 'drill_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pinciara_restore_drills' . DIRECTORY_SEPARATOR . $drillId;
    if (!is_dir($tempDir) && !mkdir($tempDir, 0750, true) && !is_dir($tempDir)) {
        throw new RuntimeException("Não foi possível criar o ambiente temporário: {$tempDir}");
    }

    $tempDbPath = $tempDir . DIRECTORY_SEPARATOR . 'restored_database.sqlite';

    // 2. Simula o processo de cópia/restauração no ambiente isolado
    if (!copy($backupPath, $tempDbPath)) {
        throw new RuntimeException("Falha ao restaurar arquivo no ambiente isolado: {$tempDbPath}");
    }

    // 3. Conecta ao banco restaurado e valida todas as regras
    $pdo = new PDO("sqlite:{$tempDbPath}", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA busy_timeout = 3000;');

    // Testes de integridade
    $integrity = (string)$pdo->query('PRAGMA integrity_check;')->fetchColumn();
    $quickCheck = (string)$pdo->query('PRAGMA quick_check;')->fetchColumn();
    $fkErrors = $pdo->query('PRAGMA foreign_key_check;')->fetchAll();

    // Verificação das tabelas essenciais do sistema
    $requiredTables = [
        'roles',
        'users',
        'sessions',
        'audit_logs',
        'leads',
        'properties',
        'lead_properties',
        'lead_assignments',
        'lead_interactions',
        'lead_follow_ups',
        'import_batches',
        'signboards',
        'signboard_movements',
        'owners',
        'owner_properties',
        'whatsapp_contact_logs',
        'import_source_rows',
    ];

    $missingTables = [];
    $tableCounts = [];

    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$table}';");
        if (!$stmt->fetch()) {
            $missingTables[] = $table;
        } else {
            $tableCounts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM {$table};")->fetchColumn();
        }
    }

    // Teste de mutação transacional controlada (garante que o banco aceita escrita e rollback)
    $writeTestOk = false;
    try {
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO audit_logs (action, ip_address) VALUES ('restore_drill.test', '127.0.0.1');");
        $pdo->rollBack();
        $writeTestOk = true;
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $writeTestOk = false;
    }

    $passed = (
        $integrity === 'ok' &&
        $quickCheck === 'ok' &&
        empty($fkErrors) &&
        empty($missingTables) &&
        $writeTestOk
    );

    // Registro do laudo no log de simulações
    $drillLogPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'restore_drill.log';
    $logLine = sprintf(
        "[%s] DRILL_ID=%s BACKUP=%s INTEGRITY=%s FK=%s WRITE_TEST=%s STATUS=%s\n",
        date('Y-m-d H:i:s'),
        $drillId,
        basename($backupPath),
        $integrity,
        empty($fkErrors) ? 'ok' : 'violations',
        $writeTestOk ? 'ok' : 'fail',
        $passed ? 'PASSED' : 'FAILED'
    );
    @file_put_contents($drillLogPath, $logLine, FILE_APPEND | LOCK_EX);

    // Limpeza segura do ambiente temporário
    unset($pdo);
    @unlink($tempDbPath);
    @rmdir($tempDir);

    $report = [
        'drill_id' => $drillId,
        'timestamp' => date('Y-m-d H:i:s'),
        'backup_tested' => basename($backupPath),
        'backup_size_formatted' => BackupService::formatBytes((int)filesize($backupPath)),
        'checks' => [
            'sqlite_integrity_check' => $integrity,
            'sqlite_quick_check' => $quickCheck,
            'foreign_key_violations' => count($fkErrors),
            'missing_required_tables' => $missingTables,
            'read_write_transaction_test' => $writeTestOk ? 'ok' : 'failed',
        ],
        'counts' => $tableCounts,
        'status' => $passed ? 'PASSED' : 'FAILED',
    ];

    if ($isJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        exit($passed ? 0 : 1);
    }

    echo "========================================================\n";
    echo "   Plataforma Pinciara — Laudo do Teste de Restauração  \n";
    echo "========================================================\n";
    echo " ID da Simulação:     {$drillId}\n";
    echo " Backup Testado:      {$report['backup_tested']} ({$report['backup_size_formatted']})\n";
    echo " Data do Laudo:       {$report['timestamp']}\n";
    echo "--------------------------------------------------------\n";
    echo " Verificações Técnicas:\n";
    echo "   [+] Integridade SQLite:        {$integrity}\n";
    echo "   [+] Quick Check:               {$quickCheck}\n";
    echo "   [+] Chaves Estrangeiras:       " . (empty($fkErrors) ? "OK (0 violações)" : "FALHA (" . count($fkErrors) . " violações)") . "\n";
    echo "   [+] Tabelas do Sistema:        " . (empty($missingTables) ? "OK (todas as " . count($requiredTables) . " tabelas presentes)" : "FALHA (" . implode(', ', $missingTables) . ")") . "\n";
    echo "   [+] Leitura / Escrita:         " . ($writeTestOk ? "OK (transação validada)" : "FALHA") . "\n";
    echo "--------------------------------------------------------\n";
    echo " Registros Validados no Backup:\n";
    foreach ($tableCounts as $tbl => $cnt) {
        printf("   - %-15s: %d registro(s)\n", $tbl, $cnt);
    }
    echo "--------------------------------------------------------\n";
    if ($passed) {
        echo " RESULTADO: [PASSED] TESTE MENSAL DE RESTAURAÇÃO VALIDADO COM SUCESSO!\n";
        echo " O backup testado é consistente, íntegro e operacionalmente recuperável.\n";
    } else {
        echo " RESULTADO: [FAILED] FALHA NA SIMULAÇÃO DE RESTAURAÇÃO!\n";
    }
    echo "========================================================\n";

    exit($passed ? 0 : 1);
} catch (Throwable $e) {
    if ($isJson) {
        echo json_encode(['status' => 'FAILED', 'error' => $e->getMessage()], JSON_PRETTY_PRINT) . "\n";
    } else {
        fwrite(STDERR, "\n[ERRO CRÍTICO NO TESTE] " . $e->getMessage() . "\n");
    }
    exit(1);
}
