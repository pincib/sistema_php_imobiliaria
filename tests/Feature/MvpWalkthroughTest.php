<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Application;
use App\Auth\Session\SessionManager;
use App\Backup\BackupService;
use App\Database\Connection;
use App\Database\Migrator;
use App\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class MvpWalkthroughTest extends TestCase
{
    private string $tempDir;
    private string $tempDb;
    private App $app;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pinciara_mvp_walk_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'backups', 0777, true);
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'logs', 0777, true);

        $this->tempDb = $this->tempDir . DIRECTORY_SEPARATOR . 'database.sqlite';
        Connection::setPath($this->tempDb);

        $_ENV['DB_PATH'] = $this->tempDb;
        $_ENV['BACKUP_DIR'] = $this->tempDir . DIRECTORY_SEPARATOR . 'backups';

        $migrator = new Migrator();
        $migrator->migrate();

        $this->app = Application::create($this->tempDir);

        SessionManager::start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        SessionManager::destroy();
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

    private function seedAdmin(): array
    {
        $pdo = Connection::getPdo();
        $hash = password_hash('AdminPass123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (name, email, password_hash, role_id, is_active) VALUES ('Admin Chefe', 'chefe@pinciara.com', '{$hash}', 1, 1)");
        $id = (int)$pdo->lastInsertId();

        return [
            'id' => $id,
            'name' => 'Admin Chefe',
            'email' => 'chefe@pinciara.com',
            'role' => 'admin',
            'role_label' => 'Administrador',
        ];
    }

    private function seedCorretor(): array
    {
        $pdo = Connection::getPdo();
        $hash = password_hash('CorretorPass123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (name, email, password_hash, role_id, is_active) VALUES ('Corretora Ana', 'ana@pinciara.com', '{$hash}', 2, 1)");
        $id = (int)$pdo->lastInsertId();

        return [
            'id' => $id,
            'name' => 'Corretora Ana',
            'email' => 'ana@pinciara.com',
            'role' => 'corretor',
            'role_label' => 'Corretor',
        ];
    }

    private function authenticate(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_role_label'] = $user['role_label'];
    }

    // =========================================================================
    // 1. FLUXO PRINCIPAL: ADMINISTRADOR
    // =========================================================================

    public function testAdminJourneyUserManagementSystemAndAudit(): void
    {
        $admin = $this->seedAdmin();
        $this->authenticate($admin);
        $csrfToken = Csrf::getToken();

        // 1. Admin acessa a tela de sistema/monitoramento
        $reqSystem = (new ServerRequestFactory())->createServerRequest('GET', '/admin/system');
        $resSystem = $this->app->handle($reqSystem);
        $this->assertSame(200, $resSystem->getStatusCode());
        $bodySystem = (string)$resSystem->getBody();
        $this->assertStringContainsString('Diagnóstico, Monitoramento e Backups', $bodySystem);
        $this->assertStringContainsString('Banco de Dados SQLite', $bodySystem);

        // 2. Admin cria um novo Corretor via formulário
        $reqCreateUser = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/users')
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'name' => 'Novo Corretor Carlos',
                'email' => 'carlos.corretor@pinciara.com',
                'password' => 'SenhaForte456',
                'role_id' => 2,
            ]);
        $resCreateUser = $this->app->handle($reqCreateUser);
        $this->assertSame(302, $resCreateUser->getStatusCode());

        $pdo = Connection::getPdo();
        $userCarlos = $pdo->query("SELECT * FROM users WHERE email = 'carlos.corretor@pinciara.com'")->fetch();
        $this->assertNotEmpty($userCarlos);
        $this->assertSame('Novo Corretor Carlos', $userCarlos['name']);
        $this->assertSame(2, (int)$userCarlos['role_id']);
        $this->assertSame(1, (int)$userCarlos['is_active']);

        // 3. Admin desativa o corretor
        $reqToggle = (new ServerRequestFactory())
            ->createServerRequest('POST', "/admin/users/{$userCarlos['id']}/toggle-active")
            ->withParsedBody(['_csrf_token' => $csrfToken]);
        $resToggle = $this->app->handle($reqToggle);
        $this->assertSame(302, $resToggle->getStatusCode());

        $userStatus = (int)$pdo->query("SELECT is_active FROM users WHERE id = {$userCarlos['id']}")->fetchColumn();
        $this->assertSame(0, $userStatus);

        // 4. Admin acessa logs de auditoria e constata o registro
        $reqAudit = (new ServerRequestFactory())->createServerRequest('GET', '/admin/audit');
        $resAudit = $this->app->handle($reqAudit);
        $this->assertSame(200, $resAudit->getStatusCode());
        $this->assertStringContainsString('user.create', (string)$resAudit->getBody());
        $this->assertStringContainsString('user.deactivated', (string)$resAudit->getBody());

        // 5. Admin aciona geração manual de backup pela interface
        $reqBackup = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/system/backup')
            ->withParsedBody(['_csrf_token' => $csrfToken]);
        $resBackup = $this->app->handle($reqBackup);
        $this->assertSame(302, $resBackup->getStatusCode());

        $backupService = new BackupService(backupDir: $this->tempDir . DIRECTORY_SEPARATOR . 'backups');
        $backups = $backupService->listBackups();
        $this->assertNotEmpty($backups);

        // 6. Admin simula teste de restauração pela interface
        $reqDrill = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/system/test-restore')
            ->withParsedBody(['_csrf_token' => $csrfToken]);
        $resDrill = $this->app->handle($reqDrill);
        $this->assertSame(302, $resDrill->getStatusCode());
    }

    // =========================================================================
    // 2. FLUXO PRINCIPAL: CORRETOR
    // =========================================================================

    public function testCorretorJourneyLeadPipelineInteractionsAndFollowUp(): void
    {
        $corretor = $this->seedCorretor();
        $this->authenticate($corretor);
        $csrfToken = Csrf::getToken();

        // 1. Corretor cadastra um novo lead
        $reqCreateLead = (new ServerRequestFactory())
            ->createServerRequest('POST', '/leads')
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'name' => 'Juliana Vendedora',
                'email' => 'juliana.vendedora@email.com',
                'phone' => '21998877665',
                'source' => 'site',
                'notes' => 'Quer vender apartamento em Icaraí',
            ]);
        $resCreateLead = $this->app->handle($reqCreateLead);
        $this->assertSame(302, $resCreateLead->getStatusCode());

        $pdo = Connection::getPdo();
        $lead = $pdo->query("SELECT * FROM leads WHERE email = 'juliana.vendedora@email.com'")->fetch();
        $this->assertNotEmpty($lead);
        $leadId = (int)$lead['id'];
        $this->assertSame('novo', $lead['stage']);

        // 2. Corretor avança estágio do lead para 'contato_realizado'
        $reqStage = (new ServerRequestFactory())
            ->createServerRequest('POST', "/leads/{$leadId}/stage")
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'stage' => 'contato_realizado',
            ]);
        $resStage = $this->app->handle($reqStage);
        $this->assertSame(302, $resStage->getStatusCode());

        $updatedStage = $pdo->query("SELECT stage FROM leads WHERE id = {$leadId}")->fetchColumn();
        $this->assertSame('contato_realizado', $updatedStage);

        // 3. Corretor registra interação cronológica no lead
        $reqInteraction = (new ServerRequestFactory())
            ->createServerRequest('POST', "/leads/{$leadId}/interactions")
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'type' => 'whatsapp',
                'notes' => 'Enviei comparativo de valores da região por WhatsApp.',
                'contacted_at' => date('Y-m-d H:i:s'),
            ]);
        $resInteraction = $this->app->handle($reqInteraction);
        $this->assertSame(302, $resInteraction->getStatusCode());

        $intCount = (int)$pdo->query("SELECT COUNT(*) FROM lead_interactions WHERE lead_id = {$leadId}")->fetchColumn();
        $this->assertSame(1, $intCount);

        // 4. Corretor agenda retorno (follow-up)
        $scheduledTime = date('Y-m-d 10:00:00', strtotime('+2 days'));
        $reqFollowUp = (new ServerRequestFactory())
            ->createServerRequest('POST', "/leads/{$leadId}/follow-ups")
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'scheduled_at' => $scheduledTime,
                'user_id' => $corretor['id'],
                'notes' => 'Verificar se avaliou a proposta',
            ]);
        $resFollowUp = $this->app->handle($reqFollowUp);
        $this->assertSame(302, $resFollowUp->getStatusCode());

        $followUp = $pdo->query("SELECT * FROM lead_follow_ups WHERE lead_id = {$leadId}")->fetch();
        $this->assertNotEmpty($followUp);
        $fuId = (int)$followUp['id'];
        $this->assertSame('pending', $followUp['status']);

        // 5. Corretor conclui o follow-up
        $reqComplete = (new ServerRequestFactory())
            ->createServerRequest('POST', "/leads/{$leadId}/follow-ups/{$fuId}/complete")
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'result_notes' => 'Cliente aceitou agendar avaliação presencial.',
            ]);
        $resComplete = $this->app->handle($reqComplete);
        $this->assertSame(302, $resComplete->getStatusCode());

        $fuStatus = $pdo->query("SELECT status FROM lead_follow_ups WHERE id = {$fuId}")->fetchColumn();
        $this->assertSame('completed', $fuStatus);
    }

    // =========================================================================
    // 3. FALHAS COMUNS E TESTES DE RESILIÊNCIA
    // =========================================================================

    public function testCorretorIsForbiddenFromAdminEndpoints(): void
    {
        $corretor = $this->seedCorretor();
        $this->authenticate($corretor);

        $forbiddenRoutes = [
            '/admin/users',
            '/admin/audit',
            '/admin/system',
            '/leads/import',
        ];

        foreach ($forbiddenRoutes as $route) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', $route);
            $response = $this->app->handle($request);
            $this->assertSame(403, $response->getStatusCode(), "Corretor não deveria ter acesso a {$route}");
        }
    }

    public function testChangeStageToPerdidoWithoutLossReasonFails(): void
    {
        $corretor = $this->seedCorretor();
        $this->authenticate($corretor);
        $csrfToken = Csrf::getToken();

        $pdo = Connection::getPdo();
        $pdo->exec("INSERT INTO leads (name, email, stage, user_id) VALUES ('Lead Teste', 'lead@perda.com', 'novo', {$corretor['id']})");
        $leadId = (int)$pdo->lastInsertId();

        // Tenta mudar para 'perdido' SEM motivo de perda
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', "/leads/{$leadId}/stage")
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'stage' => 'perdido',
                'loss_reason' => '', // Vazio de propósito
            ]);
        $response = $this->app->handle($request);

        // Deve redirecionar mantendo o estágio inalterado
        $this->assertSame(302, $response->getStatusCode());
        $currentStage = $pdo->query("SELECT stage FROM leads WHERE id = {$leadId}")->fetchColumn();
        $this->assertSame('novo', $currentStage, 'O estágio do lead não deveria mudar para perdido sem motivo.');
    }

    public function testDisabledUserLoginIsRejected(): void
    {
        $pdo = Connection::getPdo();
        $hash = password_hash('MinhaSenha123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (name, email, password_hash, role_id, is_active) VALUES ('Usuario Inativo', 'inativo@pinciara.com', '{$hash}', 2, 0)");

        $csrfToken = Csrf::getToken();

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/login')
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'email' => 'inativo@pinciara.com',
                'password' => 'MinhaSenha123',
            ]);
        $response = $this->app->handle($request);

        // Redireciona de volta para login com erro
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testMutationWithoutCsrfTokenIsRejectedWithForbidden(): void
    {
        $admin = $this->seedAdmin();
        $this->authenticate($admin);

        // 1. Requisição HTMX/AJAX sem token CSRF -> Retorna 403 Forbidden
        $requestHtmx = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/system/backup')
            ->withHeader('HX-Request', 'true')
            ->withParsedBody([]);
        $responseHtmx = $this->app->handle($requestHtmx);
        $this->assertSame(403, $responseHtmx->getStatusCode());
        $this->assertStringContainsString('Token CSRF inválido', (string)$responseHtmx->getBody());

        // 2. Formulário web padrão sem CSRF -> Redireciona com flash de erro
        $requestForm = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/system/backup')
            ->withParsedBody([]);
        $responseForm = $this->app->handle($requestForm);
        $this->assertSame(302, $responseForm->getStatusCode());
        $flashes = SessionManager::getFlashes();
        $this->assertNotEmpty($flashes['error']);
    }

    public function testRestoreRejectsCorruptedFileCleanly(): void
    {
        $service = new BackupService(
            dbPath: $this->tempDb,
            backupDir: $this->tempDir . DIRECTORY_SEPARATOR . 'backups',
            logPath: $this->tempDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'backup.log'
        );

        $corruptPath = $this->tempDir . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'corrupted.sqlite';
        file_put_contents($corruptPath, 'THIS_IS_CORRUPTED_TEXT');

        $this->expectException(\RuntimeException::class);
        $service->restoreBackup($corruptPath);
    }
}
