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
    'dest::',
    'keep::',
    'retention::',
    'external-dir::',
    'no-rotation',
    'json',
    'help',
]);

if (isset($options['help'])) {
    echo "========================================================\n";
    echo "   Plataforma Pinciara — Backup Consistente SQLite      \n";
    echo "========================================================\n\n";
    echo "Uso:\n";
    echo "  php bin/backup.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --dest=<dir>           Diretório de destino para o backup (padrão: storage/backups)\n";
    echo "  --keep=<n>             Quantidade mínima de backups preservados na rotação (padrão: 14)\n";
    echo "  --retention=<dias>     Período máximo de retenção em dias (padrão: 30)\n";
    echo "  --external-dir=<dir>   Diretório para cópia espelho fora do host\n";
    echo "  --no-rotation          Não executa expurgo de backups antigos nesta execução\n";
    echo "  --json                 Retorna o resultado em formato JSON para automação/monitoramento\n";
    echo "  --help                 Exibe esta ajuda\n";
    exit(0);
}

$isJson = isset($options['json']);
$autoRotate = !isset($options['no-rotation']);

$customDest = isset($options['dest']) ? (string)$options['dest'] : null;
$externalDir = isset($options['external-dir']) ? (string)$options['external-dir'] : null;
$keepCount = isset($options['keep']) ? (int)$options['keep'] : 14;
$retentionDays = isset($options['retention']) ? (int)$options['retention'] : 30;

try {
    $service = new BackupService(
        backupDir: $customDest,
        externalDir: $externalDir,
        defaultKeepCount: $keepCount,
        defaultRetentionDays: $retentionDays
    );

    $result = $service->createBackup(autoRotate: $autoRotate);

    if ($isJson) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    echo "========================================================\n";
    echo "   Plataforma Pinciara — Backup Concluído com Sucesso   \n";
    echo "========================================================\n";
    echo " Arquivo:         {$result['filename']}\n";
    echo " Caminho:         {$result['path']}\n";
    echo " Tamanho:         {$result['size_formatted']} ({$result['size_bytes']} bytes)\n";
    echo " Duração:         {$result['duration_seconds']} s\n";
    echo " Integridade:     {$result['integrity']}\n";
    echo " SHA256:          {$result['sha256']}\n";
    echo " Data/Hora:       {$result['created_at']}\n";

    if (!empty($result['rotated_files'])) {
        echo " Rotação:         " . count($result['rotated_files']) . " backup(s) antigo(s) expurgado(s)\n";
        foreach ($result['rotated_files'] as $f) {
            echo "   [-] {$f}\n";
        }
    } else {
        echo " Rotação:         Nenhum arquivo expirado para expurgo\n";
    }

    if ($result['external_copy']['status'] === 'success') {
        echo " Cópia Fora Host: Concluída com sucesso -> {$result['external_copy']['destination']}\n";
    } elseif ($result['external_copy']['status'] === 'error') {
        echo " Cópia Fora Host: [AVISO] Falhou: {$result['external_copy']['error']}\n";
    } else {
        echo " Cópia Fora Host: Não configurada (BACKUP_EXTERNAL_DIR vazio)\n";
    }

    echo "========================================================\n";
    exit(0);
} catch (Throwable $e) {
    if ($isJson) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        fwrite(STDERR, "\n[FALHA CRÍTICA] Erro ao executar backup do banco: " . $e->getMessage() . "\n");
    }
    exit(1);
}
