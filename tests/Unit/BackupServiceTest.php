<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Backup\BackupService;
use App\Database\Connection;
use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BackupServiceTest extends TestCase
{
    private string $tempDir;
    private string $dbPath;
    private string $backupDir;
    private string $extDir;
    private string $logPath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pinciara_test_backup_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->dbPath = $this->tempDir . DIRECTORY_SEPARATOR . 'test_db.sqlite';
        $this->backupDir = $this->tempDir . DIRECTORY_SEPARATOR . 'backups';
        $this->extDir = $this->tempDir . DIRECTORY_SEPARATOR . 'external';
        $this->logPath = $this->tempDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'backup.log';

        mkdir($this->backupDir, 0777, true);
        mkdir($this->extDir, 0777, true);
        mkdir(dirname($this->logPath), 0777, true);

        // Configura conexão temporária
        Connection::setPath($this->dbPath);
        $migrator = new Migrator();
        $migrator->migrate();
    }

    protected function tearDown(): void
    {
        Connection::reset();
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function testCreateBackupGeneratesConsistentValidSqliteFile(): void
    {
        // Insere dado de teste
        $pdo = Connection::getPdo();
        $pdo->exec("INSERT INTO leads (name, email, phone, stage) VALUES ('Lead Teste', 'lead@teste.com', '21999998888', 'novo')");

        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath
        );

        $result = $service->createBackup(autoRotate: false);

        $this->assertSame('success', $result['status']);
        $this->assertFileExists($result['path']);
        $this->assertGreaterThan(0, $result['size_bytes']);
        $this->assertSame('ok', $result['integrity']);
        $this->assertNotEmpty($result['sha256']);
        $this->assertFileExists($this->logPath);

        // Valida que o backup contém o lead inserido
        $backupPdo = new PDO("sqlite:{$result['path']}");
        $stmt = $backupPdo->query("SELECT name FROM leads WHERE email = 'lead@teste.com'");
        $this->assertSame('Lead Teste', $stmt->fetchColumn());
    }

    public function testVerifyBackupIdentifiesCorruptedAndValidFiles(): void
    {
        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath
        );

        $backup = $service->createBackup(autoRotate: false);
        $validCheck = $service->verifyBackup($backup['path']);

        $this->assertTrue($validCheck['valid']);
        $this->assertSame('ok', $validCheck['integrity']);
        $this->assertSame('ok', $validCheck['foreign_keys']);

        // Testa arquivo inválido/corrompido
        $corruptFile = $this->backupDir . DIRECTORY_SEPARATOR . 'corrupted.sqlite';
        file_put_contents($corruptFile, 'NOT_A_VALID_SQLITE_HEADER_CORRUPTED_DATA');

        $corruptCheck = $service->verifyBackup($corruptFile);
        $this->assertFalse($corruptCheck['valid']);
    }

    public function testRotationPreservesKeepCountAndPrunesExpiredBackups(): void
    {
        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath,
            defaultKeepCount: 3,
            defaultRetentionDays: 5
        );

        // Cria 5 arquivos de backup simulando diferentes datas
        $now = time();
        for ($i = 1; $i <= 5; $i++) {
            $filename = "backup_test_{$i}.sqlite";
            $path = $this->backupDir . DIRECTORY_SEPARATOR . $filename;
            copy($this->dbPath, $path);

            // Backups 4 e 5 têm mais de 10 dias de idade
            if ($i >= 4) {
                touch($path, $now - (15 * 86400));
            } else {
                touch($path, $now - ($i * 3600));
            }
        }

        // Executa rotação: keep=3, retention=5 dias
        $pruned = $service->rotateBackups(keepCount: 3, retentionDays: 5);

        // Pelo menos os backups expirados além do keepCount devem ser removidos
        $this->assertContains('backup_test_4.sqlite', $pruned);
        $this->assertContains('backup_test_5.sqlite', $pruned);
        $this->assertFileDoesNotExist($this->backupDir . DIRECTORY_SEPARATOR . 'backup_test_4.sqlite');
        $this->assertFileDoesNotExist($this->backupDir . DIRECTORY_SEPARATOR . 'backup_test_5.sqlite');

        // Os 3 mais recentes permanecem intactos
        $this->assertFileExists($this->backupDir . DIRECTORY_SEPARATOR . 'backup_test_1.sqlite');
        $this->assertFileExists($this->backupDir . DIRECTORY_SEPARATOR . 'backup_test_2.sqlite');
        $this->assertFileExists($this->backupDir . DIRECTORY_SEPARATOR . 'backup_test_3.sqlite');
    }

    public function testCopyOffHostMirrorsFileWithMatchingChecksum(): void
    {
        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            externalDir: $this->extDir,
            logPath: $this->logPath
        );

        $backup = $service->createBackup(autoRotate: false);

        $this->assertSame('success', $backup['external_copy']['status']);
        $this->assertFileExists($backup['external_copy']['destination']);

        $sourceHash = hash_file('sha256', $backup['path']);
        $destHash = hash_file('sha256', $backup['external_copy']['destination']);
        $this->assertSame($sourceHash, $destHash);
    }

    public function testRestoreReplacesDatabaseAndCreatesSafetySnapshot(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec("INSERT INTO leads (name, email, phone) VALUES ('Lead Original', 'original@teste.com', '21988887777')");

        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath
        );

        $backup = $service->createBackup(autoRotate: false);

        // Agora altera drasticamente o banco ativo (simulando perda acidental)
        $pdo->exec("DELETE FROM leads;");
        $pdo->exec("INSERT INTO leads (name, email, phone) VALUES ('Lead Corrompido', 'corrompido@teste.com', '000')");

        $this->assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE email = 'original@teste.com'")->fetchColumn());
        unset($pdo);

        // Executa a restauração a partir do backup
        $restoreResult = $service->restoreBackup($backup['path'], createSafetySnapshot: true);

        $this->assertSame('success', $restoreResult['status']);
        $this->assertNotNull($restoreResult['safety_snapshot']);
        $this->assertFileExists($this->backupDir . DIRECTORY_SEPARATOR . $restoreResult['safety_snapshot']);

        // Reconecta ao banco restaurado e confere integridade
        $restoredPdo = Connection::getPdo();
        $leadName = $restoredPdo->query("SELECT name FROM leads WHERE email = 'original@teste.com'")->fetchColumn();
        $this->assertSame('Lead Original', $leadName);

        $corrompidoCount = (int)$restoredPdo->query("SELECT COUNT(*) FROM leads WHERE email = 'corrompido@teste.com'")->fetchColumn();
        $this->assertSame(0, $corrompidoCount);
    }

    public function testRestoreRejectsCorruptedFileWithoutOverwritingActiveDatabase(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec("INSERT INTO leads (name, email) VALUES ('Lead Seguro', 'seguro@teste.com')");

        $corruptedFile = $this->backupDir . DIRECTORY_SEPARATOR . 'fake_backup.sqlite';
        file_put_contents($corruptedFile, 'CORRUPTED_GARBAGE_PAYLOAD');

        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/inválido ou está corrompido/i');

        try {
            $service->restoreBackup($corruptedFile);
        } finally {
            // Garante que o banco ativo não foi destruído
            $currentPdo = Connection::getPdo();
            $this->assertSame(1, (int)$currentPdo->query("SELECT COUNT(*) FROM leads WHERE email = 'seguro@teste.com'")->fetchColumn());
        }
    }

    public function testRestoreRejectsValidSqliteFileMissingRequiredTablesBeforeOverwritingActiveDatabase(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec("INSERT INTO leads (name, email) VALUES ('Lead Seguro', 'seguro@teste.com')");

        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath
        );
        $completeBackup = $service->createBackup(autoRotate: false);
        $incompleteFile = $this->backupDir . DIRECTORY_SEPARATOR . 'incomplete.sqlite';
        copy($completeBackup['path'], $incompleteFile);
        $incompletePdo = new PDO("sqlite:{$incompleteFile}");
        $incompletePdo->exec('DROP TABLE import_source_rows;');
        unset($incompletePdo);

        $verification = $service->verifyBackup($incompleteFile);
        $this->assertFalse($verification['valid']);
        $this->assertContains('import_source_rows', $verification['missing_tables']);

        try {
            $service->restoreBackup($incompleteFile);
            $this->fail('A restauração deveria rejeitar um SQLite sem as tabelas essenciais.');
        } catch (RuntimeException) {
            $currentPdo = Connection::getPdo();
            $this->assertSame(
                1,
                (int)$currentPdo->query("SELECT COUNT(*) FROM leads WHERE email = 'seguro@teste.com'")->fetchColumn()
            );
        }
    }

    public function testRestoreAbortsWhenSafetySnapshotCannotBeCreated(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec("INSERT INTO leads (name, email) VALUES ('Estado atual', 'atual@teste.com')");

        $service = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath
        );
        $backup = $service->createBackup(autoRotate: false);

        $pdo->exec("UPDATE leads SET name = 'Dados que devem ser preservados' WHERE email = 'atual@teste.com'");
        unset($pdo);

        // Um arquivo no lugar do diretório impede tanto VACUUM INTO quanto a cópia de fallback.
        $blockedBackupDir = $this->tempDir . DIRECTORY_SEPARATOR . 'not_a_directory';
        file_put_contents($blockedBackupDir, 'blocked');
        $blockedService = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $blockedBackupDir,
            logPath: $this->logPath
        );

        try {
            $blockedService->restoreBackup($backup['path'], createSafetySnapshot: true);
            $this->fail('A restauração deveria parar quando o snapshot de segurança não pode ser criado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('snapshot', strtolower($exception->getMessage()));
        }

        $currentPdo = Connection::getPdo();
        $this->assertSame(
            'Dados que devem ser preservados',
            $currentPdo->query("SELECT name FROM leads WHERE email = 'atual@teste.com'")->fetchColumn()
        );
    }
}
