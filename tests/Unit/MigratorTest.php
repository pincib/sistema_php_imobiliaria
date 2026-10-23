<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database\Connection;
use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private string $tempDb;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_mig_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);
    }

    protected function tearDown(): void
    {
        Connection::reset();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function testMigrationsApplyRepetitivelyOnFreshDatabase(): void
    {
        $migrator = new Migrator();

        // 1. Detecta migrações pendentes em banco novo
        $pending = $migrator->getPending();
        $this->assertNotEmpty($pending);
        $this->assertContains('008_create_signboards.sql', $pending);

        // 2. Executa as migrações
        $applied = $migrator->migrate();
        $this->assertSame($pending, $applied);

        // 3. Verifica se as tabelas foram criadas
        $pdo = Connection::getPdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('roles', $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('sessions', $tables);
        $this->assertContains('audit_logs', $tables);
        $this->assertContains('migrations', $tables);
        $this->assertContains('leads', $tables);
        $this->assertContains('properties', $tables);
        $this->assertContains('lead_properties', $tables);
        $this->assertContains('lead_assignments', $tables);
        $this->assertContains('lead_interactions', $tables);
        $this->assertContains('lead_follow_ups', $tables);
        $this->assertContains('import_batches', $tables);

        // 4. Verifica se papéis padrão foram semeados
        $roles = $pdo->query('SELECT name FROM roles ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['admin', 'corretor'], $roles);

        // 5. Verifica se índices essenciais existem
        $indexes = $pdo->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('idx_users_email', $indexes);
        $this->assertContains('idx_sessions_user_id', $indexes);
        $this->assertContains('idx_audit_logs_action', $indexes);

        // 6. Idempotência: rodar novamente não deve executar nada
        $pendingAgain = $migrator->getPending();
        $this->assertEmpty($pendingAgain);

        $appliedAgain = $migrator->migrate();
        $this->assertEmpty($appliedAgain);
    }

    public function testForeignKeysConstraintIsEnforcedOnUsers(): void
    {
        $migrator = new Migrator();
        $migrator->migrate();

        $pdo = Connection::getPdo();

        $this->expectException(\PDOException::class);
        // Tenta inserir usuário com role_id inexistente (999)
        $pdo->exec("
            INSERT INTO users (name, email, password_hash, role_id)
            VALUES ('Teste', 'teste@example.com', 'hash', 999)
        ");
    }
}
