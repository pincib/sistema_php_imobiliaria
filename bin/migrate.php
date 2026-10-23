<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Migrator;
use Dotenv\Dotenv;

// Carrega variáveis de ambiente se existir o arquivo .env
if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

echo "=== Plataforma Pinciara: Migrações SQLite ===\n";

try {
    $migrator = new Migrator();
    $pending = $migrator->getPending();

    if (empty($pending)) {
        echo "[OK] Nenhuma migração pendente. Banco de dados está atualizado.\n";
        exit(0);
    }

    echo "Migrações pendentes detectadas: " . count($pending) . "\n";
    $applied = $migrator->migrate();

    foreach ($applied as $migration) {
        echo " [+] Executada: {$migration}\n";
    }

    echo "\n[SUCESSO] Todas as migrações foram aplicadas com sucesso!\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\n[ERRO] Falha ao executar migrações: " . $e->getMessage() . "\n");
    exit(1);
}
