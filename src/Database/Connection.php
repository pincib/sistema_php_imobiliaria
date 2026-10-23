<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;
use Throwable;

final class Connection
{
    private static ?PDO $pdo = null;
    private static ?string $customPath = null;

    private function __construct()
    {
    }

    /**
     * Permite definir um caminho customizado (ex: para testes em memória ou arquivo temporário).
     */
    public static function setPath(?string $path): void
    {
        self::$customPath = $path;
        self::$pdo = null;
    }

    /**
     * Fecha a instância ativa do PDO liberando locks em arquivos sem apagar o caminho customizado.
     */
    public static function close(): void
    {
        if (self::$pdo !== null) {
            try {
                self::$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE);');
            } catch (\Throwable) {
            }
            self::$pdo = null;
        }
    }

    /**
     * Reseta a conexão atual (útil em testes ou scripts CLI).
     */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$customPath = null;
    }

    /**
     * Obtém ou inicializa a conexão centralizada PDO SQLite com WAL, Foreign Keys e Busy Timeout.
     */
    public static function getPdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dbPath = self::$customPath ?? $_ENV['DB_PATH'] ?? getenv('DB_PATH') ?: 'storage/database.sqlite';

        // Resolve caminho absoluto se for relativo e não for :memory:
        if ($dbPath !== ':memory:' && !str_starts_with($dbPath, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dbPath)) {
            $baseDir = dirname(__DIR__, 2);
            $dbPath = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbPath);
        }

        if ($dbPath !== ':memory:') {
            $dir = dirname($dbPath);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Não foi possível criar o diretório de dados: {$dir}");
            }
        }

        $dsn = "sqlite:{$dbPath}";
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Diretrizes essenciais de integridade e concorrência no SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        if ($dbPath !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA synchronous = NORMAL;');
        }

        self::$pdo = $pdo;

        return self::$pdo;
    }

    /**
     * Executa operações dentro de uma transação curta e segura.
     * Caso ocorra qualquer exceção, a transação é revertida imediatamente.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getPdo();
        $isNested = $pdo->inTransaction();

        if (!$isNested) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if (!$isNested && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if (!$isNested && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
