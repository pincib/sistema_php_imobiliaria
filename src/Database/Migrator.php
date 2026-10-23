<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class Migrator
{
    private string $migrationsPath;

    public function __construct(?string $migrationsPath = null)
    {
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    /**
     * Inicializa a tabela de controle de migrações se não existir.
     */
    public function ensureMigrationTable(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    /**
     * Retorna a lista de nomes de migrações já executadas.
     *
     * @return list<string>
     */
    public function getExecuted(): array
    {
        $this->ensureMigrationTable();
        $pdo = Connection::getPdo();
        $stmt = $pdo->query('SELECT migration FROM migrations ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retorna a lista de arquivos de migração pendentes.
     *
     * @return list<string>
     */
    public function getPending(): array
    {
        $executed = $this->getExecuted();
        $allFiles = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.sql');

        if ($allFiles === false) {
            return [];
        }

        $pending = [];
        sort($allFiles, SORT_STRING);

        foreach ($allFiles as $file) {
            $filename = basename($file);
            if (!in_array($filename, $executed, true)) {
                $pending[] = $filename;
            }
        }

        return $pending;
    }

    /**
     * Aplica todas as migrações pendentes em transações curtas.
     *
     * @return list<string> Lista das migrações recém-executadas.
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();
        $pending = $this->getPending();
        $applied = [];

        foreach ($pending as $migrationFile) {
            $filePath = $this->migrationsPath . DIRECTORY_SEPARATOR . $migrationFile;
            $sql = file_get_contents($filePath);

            if ($sql === false) {
                throw new RuntimeException("Falha ao ler arquivo de migração: {$filePath}");
            }

            // Executa a migração individual e registra na tabela dentro de uma transação
            Connection::transaction(function (PDO $pdo) use ($sql, $migrationFile): void {
                $pdo->exec($sql);
                $stmt = $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
                $stmt->execute([':migration' => $migrationFile]);
            });

            $applied[] = $migrationFile;
        }

        return $applied;
    }
}
