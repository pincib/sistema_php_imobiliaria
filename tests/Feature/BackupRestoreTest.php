<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Audit\AuditService;
use App\Backup\BackupService;
use App\Database\Connection;
use App\Database\Migrator;
use App\Modules\Leads\Services\FollowUpService;
use App\Modules\Leads\Services\InteractionService;
use App\Modules\Leads\Services\LeadService;
use PDO;
use PHPUnit\Framework\TestCase;

final class BackupRestoreTest extends TestCase
{
    private string $tempDir;
    private string $dbPath;
    private string $backupDir;
    private string $logPath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pinciara_feat_backup_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->dbPath = $this->tempDir . DIRECTORY_SEPARATOR . 'active_db.sqlite';
        $this->backupDir = $this->tempDir . DIRECTORY_SEPARATOR . 'backups';
        $this->logPath = $this->tempDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'backup.log';

        mkdir($this->backupDir, 0777, true);
        mkdir(dirname($this->logPath), 0777, true);

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

    public function testEndToEndDisasterRecoveryRestoresAllEntitiesAndPreservesIntegrity(): void
    {
        $pdo = Connection::getPdo();

        // 1. Popula banco com dados operacionais reais do CRM
        // Cria usuário Admin e Corretor
        $pdo->exec("INSERT INTO users (name, email, password_hash, role_id, is_active) VALUES ('Admin Principal', 'admin@pinciara.com', 'hash', 1, 1)");
        $adminId = (int)$pdo->lastInsertId();

        $pdo->exec("INSERT INTO users (name, email, password_hash, role_id, is_active) VALUES ('Corretor Roberto', 'roberto@pinciara.com', 'hash', 2, 1)");
        $corretorId = (int)$pdo->lastInsertId();

        // Cria Leads
        $leadService = new LeadService();
        $res1 = $leadService->createLead([
            'name' => 'Carlos Investidor',
            'email' => 'carlos@invest.com',
            'phone' => '21988881111',
            'user_id' => $corretorId,
            'notes' => 'Interesse em cobertura em Icaraí',
        ], $corretorId);
        $this->assertTrue($res1['success']);
        $lead1Id = (int)$res1['lead_id'];

        $res2 = $leadService->createLead([
            'name' => 'Mariana Compradora',
            'email' => 'mariana@email.com',
            'phone' => '21977772222',
            'user_id' => $corretorId,
        ], $corretorId);
        $this->assertTrue($res2['success']);
        $lead2Id = (int)$res2['lead_id'];

        // Atualiza Mariana para estágio 'perdido' com motivo
        $stageRes = $leadService->changeStage($lead2Id, 'perdido', 'Comprou com outra imobiliária', $corretorId);
        $this->assertTrue($stageRes['success']);

        // Cria Interações e Follow-ups
        $interactionService = new InteractionService();
        $intRes = $interactionService->createInteraction($lead1Id, [
            'type' => 'ligacao',
            'notes' => 'Ligação produtiva. Apresentei opções no Jardim Icaraí.',
            'contacted_at' => date('Y-m-d H:i:s'),
        ], $corretorId);
        $this->assertTrue($intRes['success']);

        $followUpService = new FollowUpService();
        $fuRes = $followUpService->scheduleFollowUp($lead1Id, [
            'scheduled_at' => date('Y-m-d 15:00:00', strtotime('+1 day')),
            'user_id' => $corretorId,
            'notes' => 'Retornar para agendar visita presencial',
        ], $adminId);
        $this->assertTrue($fuRes['success']);

        // Registra evento de auditoria
        AuditService::log('test.initial_seed', 'lead', (string)$lead1Id, null, ['status' => 'ok'], $adminId);

        // 2. Executa Backup Diário Consistente
        $backupService = new BackupService(
            dbPath: $this->dbPath,
            backupDir: $this->backupDir,
            logPath: $this->logPath
        );

        $backupResult = $backupService->createBackup(autoRotate: false);
        $this->assertSame('success', $backupResult['status']);
        $this->assertSame('ok', $backupResult['integrity']);
        $this->assertFileExists($backupResult['path']);

        // 3. Simula Perda de Dados Catastrófica (Host Failure / Exclusão Inadvertida)
        $pdo->exec("DELETE FROM lead_follow_ups;");
        $pdo->exec("DELETE FROM lead_interactions;");
        $pdo->exec("DELETE FROM leads;");
        $pdo->exec("DELETE FROM users WHERE email = 'roberto@pinciara.com';");

        // Confirma que os dados foram destruídos na base ativa
        $this->assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn());
        $this->assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM lead_interactions")->fetchColumn());
        $this->assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM lead_follow_ups")->fetchColumn());
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn());

        // Libera handles de serviços e PDO antes da restauração
        unset($pdo, $leadService, $interactionService, $followUpService);
        gc_collect_cycles();

        // 4. Executa Restauração Segura do Backup
        $restoreResult = $backupService->restoreBackup($backupResult['path'], createSafetySnapshot: true);

        $this->assertSame('success', $restoreResult['status']);
        $this->assertNotNull($restoreResult['safety_snapshot']);
        $this->assertFileExists($this->backupDir . DIRECTORY_SEPARATOR . $restoreResult['safety_snapshot']);

        // 5. Valida que 100% dos dados, relacionamentos e restrições foram restaurados
        $restoredPdo = Connection::getPdo();

        // Usuários
        $usersCount = (int)$restoredPdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $this->assertSame(2, $usersCount);

        // Leads
        $leadsCount = (int)$restoredPdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
        $this->assertSame(2, $leadsCount);

        $carlos = $restoredPdo->query("SELECT * FROM leads WHERE email = 'carlos@invest.com'")->fetch();
        $this->assertNotEmpty($carlos);
        $this->assertSame('Carlos Investidor', $carlos['name']);
        $this->assertSame((int)$corretorId, (int)$carlos['user_id']);

        $mariana = $restoredPdo->query("SELECT * FROM leads WHERE email = 'mariana@email.com'")->fetch();
        $this->assertSame('perdido', $mariana['stage']);
        $this->assertSame('Comprou com outra imobiliária', $mariana['loss_reason']);

        // Interações
        $interactions = $restoredPdo->query("SELECT * FROM lead_interactions WHERE lead_id = {$lead1Id}")->fetchAll();
        $this->assertCount(1, $interactions);
        $this->assertSame('ligacao', $interactions[0]['type']);
        $this->assertStringContainsString('Ligação produtiva', $interactions[0]['notes']);

        // Follow-ups
        $followUps = $restoredPdo->query("SELECT * FROM lead_follow_ups WHERE lead_id = {$lead1Id}")->fetchAll();
        $this->assertCount(1, $followUps);
        $this->assertSame('pending', $followUps[0]['status']);
        $this->assertStringContainsString('Retornar para agendar visita', $followUps[0]['notes']);

        // Auditoria
        $auditEntry = $restoredPdo->query("SELECT * FROM audit_logs WHERE action = 'backup.restore'")->fetch();
        $this->assertNotEmpty($auditEntry);

        // Integridade final e chaves estrangeiras
        $this->assertSame('ok', (string)$restoredPdo->query('PRAGMA integrity_check;')->fetchColumn());
        $this->assertSame('ok', (string)$restoredPdo->query('PRAGMA quick_check;')->fetchColumn());
        $this->assertEmpty($restoredPdo->query('PRAGMA foreign_key_check;')->fetchAll());
    }
}
