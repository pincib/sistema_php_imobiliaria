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
    'force',
    'confirm',
    'no-safety-snapshot',
    'list',
    'verify-only',
    'help',
]);

$service = new BackupService();

if (isset($options['help'])) {
    echo "========================================================\n";
    echo "   Plataforma Pinciara — Restauração do SQLite          \n";
    echo "========================================================\n\n";
    echo "Uso:\n";
    echo "  php bin/restore.php [arquivo-de-backup.sqlite] [opções]\n\n";
    echo "Opções:\n";
    echo "  --file=<caminho>         Caminho para o arquivo .sqlite a ser restaurado\n";
    echo "  --force / --confirm      Executa a restauração sem solicitar confirmação interativa\n";
    echo "  --no-safety-snapshot     Não cria o snapshot de segurança pré-restauração\n";
    echo "  --list                   Lista todos os backups disponíveis e sai\n";
    echo "  --verify-only            Apenas valida a integridade do backup sem restaurar\n";
    echo "  --help                   Exibe esta ajuda\n";
    exit(0);
}

if (isset($options['list'])) {
    echo "=== Backups Disponíveis em {$service->getBackupDir()} ===\n\n";
    $backups = $service->listBackups(includeSnapshots: true);

    if (empty($backups)) {
        echo "Nenhum backup encontrado no diretório.\n";
        exit(0);
    }

    printf("%-35s | %-10s | %-19s | %-10s\n", "Nome do Arquivo", "Tamanho", "Criado em", "Tipo");
    echo str_repeat('-', 82) . "\n";
    foreach ($backups as $b) {
        $type = $b['is_snapshot'] ? 'Snapshot' : 'Backup';
        printf("%-35s | %-10s | %-19s | %-10s\n", $b['filename'], $b['size_formatted'], $b['created_at'], $type);
    }
    exit(0);
}

// Determina o arquivo a ser restaurado
$backupPath = $options['file'] ?? ($argv[1] ?? null);

// Se o primeiro argumento for uma flag (começa com --), ignora
if ($backupPath !== null && str_starts_with($backupPath, '--')) {
    $backupPath = null;
}

if (empty($backupPath)) {
    $latest = $service->getLatestBackup();
    if (!$latest) {
        fwrite(STDERR, "[ERRO] Nenhum backup especificado e nenhum backup existente encontrado em {$service->getBackupDir()}.\n");
        exit(1);
    }

    echo "Nenhum arquivo especificado. Backup mais recente detectado: {$latest['filename']} ({$latest['size_formatted']})\n";
    $backupPath = $latest['path'];
}

// Resolve caminho relativo
if (!file_exists($backupPath)) {
    // Tenta dentro da pasta de backups
    $candidate = $service->getBackupDir() . DIRECTORY_SEPARATOR . basename($backupPath);
    if (file_exists($candidate)) {
        $backupPath = $candidate;
    } else {
        fwrite(STDERR, "[ERRO] Arquivo de backup não localizado: {$backupPath}\n");
        exit(1);
    }
}

echo "========================================================\n";
echo "   Plataforma Pinciara — Restauração do Banco SQLite    \n";
echo "========================================================\n";
echo " Arquivo de Origem: " . basename($backupPath) . "\n";
echo " Caminho Completo:  {$backupPath}\n";

// Executa verificação prévia
echo "\n[1/3] Verificando integridade e consistência do arquivo...\n";
$verification = $service->verifyBackup($backupPath);

if (!$verification['valid']) {
    $err = $verification['error'] ?? "Integridade: {$verification['integrity']}, FK: {$verification['foreign_keys']}";
    fwrite(STDERR, "\n[FALHA] O arquivo informado NÃO é um backup SQLite válido ou íntegro!\nDetalhes: {$err}\n");
    exit(1);
}

echo " [+] PRAGMA integrity_check: {$verification['integrity']}\n";
echo " [+] PRAGMA quick_check:     {$verification['quick_check']}\n";
echo " [+] PRAGMA foreign_keys:    {$verification['foreign_keys']}\n";
echo " [+] Total de tabelas:       {$verification['tables_count']}\n";
echo " [+] Usuários cadastrados:   {$verification['users_count']}\n";
echo " [+] Leads cadastrados:      {$verification['leads_count']}\n";

if (isset($options['verify-only'])) {
    echo "\n[OK] Verificação concluída com sucesso. (--verify-only ativo, nenhuma alteração efetuada)\n";
    exit(0);
}

// Solicita confirmação explícita a menos que --force/--confirm tenha sido informado
$isForce = isset($options['force']) || isset($options['confirm']);
if (!$isForce) {
    echo "\n--------------------------------------------------------\n";
    echo " [ATENÇÃO CRÍTICA]\n";
    echo " A restauração substituirá COMPLETAMENTE o banco de dados ativo!\n";
    echo " Todos os dados inseridos após este backup serão perdidos se não salvos.\n";
    echo " (Um snapshot de segurança será criado automaticamente antes da substituição)\n";
    echo "--------------------------------------------------------\n";
    echo "Para confirmar, digite exatamente 'RESTAURAR' e pressione Enter: ";

    $handle = fopen('php://stdin', 'r');
    $confirmation = trim((string)fgets($handle));

    if ($confirmation !== 'RESTAURAR') {
        echo "\nOperação de restauração cancelada pelo usuário.\n";
        exit(0);
    }
}

$createSnapshot = !isset($options['no-safety-snapshot']);
echo "\n[2/3] Executando processo de restauração segura...\n";

try {
    $result = $service->restoreBackup($backupPath, createSafetySnapshot: $createSnapshot);

    echo "\n[3/3] Validação pós-restauração concluída!\n";
    echo "========================================================\n";
    echo " [SUCESSO] BANCO DE DADOS RESTAURADO COM SUCESSO!        \n";
    echo "========================================================\n";
    echo " Arquivo Restaurado: {$result['restored_file']}\n";
    if (!empty($result['safety_snapshot'])) {
        echo " Snapshot de Guarda: {$result['safety_snapshot']} (backup do estado anterior mantido)\n";
    }
    echo " Tabelas Ativas:     {$result['tables_restored']}\n";
    echo " Usuários Ativos:    {$result['users_count']}\n";
    echo " Leads Ativos:       {$result['leads_count']}\n";
    echo " Concluído em:       {$result['restored_at']}\n";
    echo "========================================================\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\n[FALHA CRÍTICA NA RESTAURAÇÃO] " . $e->getMessage() . "\n");
    exit(1);
}
