<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConnectionTest extends TestCase
{
    private string $tempDb;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_conn_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);
    }

    protected function tearDown(): void
    {
        Connection::reset();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function testConnectionEnforcesWalForeignKeysAndBusyTimeout(): void
    {
        $pdo = Connection::getPdo();

        $journalMode = $pdo->query('PRAGMA journal_mode;')->fetchColumn();
        $this->assertSame('wal', strtolower((string)$journalMode));

        $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys;')->fetchColumn();
        $this->assertSame(1, $foreignKeys);

        $busyTimeout = (int)$pdo->query('PRAGMA busy_timeout;')->fetchColumn();
        $this->assertSame(5000, $busyTimeout);
    }

    public function testTransactionRollsBackOnException(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec('CREATE TABLE test_tx (id INTEGER PRIMARY KEY, name TEXT)');

        try {
            Connection::transaction(function (PDO $pdo) {
                $pdo->exec("INSERT INTO test_tx (name) VALUES ('Item 1')");
                throw new RuntimeException('Simulated failure');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated failure', $e->getMessage());
        }

        $count = (int)$pdo->query('SELECT COUNT(*) FROM test_tx')->fetchColumn();
        $this->assertSame(0, $count, 'A transação deveria ter sido revertida completamente.');
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec('CREATE TABLE test_tx (id INTEGER PRIMARY KEY, name TEXT)');

        $result = Connection::transaction(function (PDO $pdo) {
            $pdo->exec("INSERT INTO test_tx (name) VALUES ('Item Salvo')");
            return 'sucesso';
        });

        $this->assertSame('sucesso', $result);
        $count = (int)$pdo->query('SELECT COUNT(*) FROM test_tx')->fetchColumn();
        $this->assertSame(1, $count);
    }
}
