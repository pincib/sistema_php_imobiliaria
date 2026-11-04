<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Application;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Database\Migrator;
use App\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class OwnerVerificationTest extends TestCase
{
    private string $tempDb;
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_owner_verif_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();

        $hash = password_hash('SenhaSegura123!', PASSWORD_DEFAULT);
        $this->pdo->exec("
            INSERT INTO users (id, name, email, password_hash, role_id, is_active) VALUES
            (1, 'Admin Pinciara', 'admin@pinciara.com', '{$hash}', 1, 1),
            (2, 'Roberto Corretor', 'roberto@pinciara.com', '{$hash}', 2, 1);
        ");

        $this->app = Application::create();
        SessionManager::start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        SessionManager::destroy();
        Connection::reset();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    private function authenticateAs(int $userId, string $name, string $role): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_role_label'] = ucfirst($role);
        $_SESSION['last_activity'] = time();
    }

    public function testOwnerAgingRuleAfterThirtyDays(): void
    {
        $this->authenticateAs(1, 'Admin Pinciara', 'admin');

        $fiveDaysAgo = date('Y-m-d H:i:s', strtotime('-5 days'));
        $thirtyFiveDaysAgo = date('Y-m-d H:i:s', strtotime('-35 days'));

        $this->pdo->exec("
            INSERT INTO owners (id, name, email, phone, last_verified_at, verified_by_user_id) VALUES
            (1, 'Proprietário Recente', 'recente@teste.com', '21988887777', '{$fiveDaysAgo}', 1),
            (2, 'Proprietário Expirado', 'expirado@teste.com', '21977776666', '{$thirtyFiveDaysAgo}', 1),
            (3, 'Proprietário Nunca Verificado', 'nunca@teste.com', '21966665555', NULL, NULL);
        ");

        // 1. Aba 'desatualizados' deve listar os IDs 2 e 3 (expirado e nunca verificado)
        $reqDesat = (new ServerRequestFactory())->createServerRequest('GET', '/owners?tab=desatualizados');
        $respDesat = $this->app->handle($reqDesat);
        $this->assertSame(200, $respDesat->getStatusCode());
        $bodyDesat = (string)$respDesat->getBody();

        $this->assertStringContainsString('Proprietário Expirado', $bodyDesat);
        $this->assertStringContainsString('Proprietário Nunca Verificado', $bodyDesat);
        $this->assertStringNotContainsString('Proprietário Recente', $bodyDesat);

        // 2. Aba 'atualizados' deve listar apenas o ID 1 (verificado há 5 dias)
        $reqAtuat = (new ServerRequestFactory())->createServerRequest('GET', '/owners?tab=atualizados');
        $respAtuat = $this->app->handle($reqAtuat);
        $this->assertSame(200, $respAtuat->getStatusCode());
        $bodyAtuat = (string)$respAtuat->getBody();

        $this->assertStringContainsString('Proprietário Recente', $bodyAtuat);
        $this->assertStringNotContainsString('Proprietário Expirado', $bodyAtuat);
        $this->assertStringNotContainsString('Proprietário Nunca Verificado', $bodyAtuat);
    }

    public function testMarkVerifiedEndpointUpdatesTimestampAndAudits(): void
    {
        $this->authenticateAs(2, 'Roberto Corretor', 'corretor');

        $this->pdo->exec("
            INSERT INTO owners (id, name, email, phone, last_verified_at) VALUES
            (10, 'Proprietário Para Atualizar', 'atualizar@teste.com', '21955554444', NULL);
        ");

        $csrf = Csrf::getToken();

        // Executa ação de marcar como atualizado
        $req = (new ServerRequestFactory())->createServerRequest('POST', '/owners/10/verify')
            ->withParsedBody([
                '_csrf_token' => $csrf,
                'return_to' => '/owners/10',
            ]);

        $resp = $this->app->handle($req);
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame('/owners/10', $resp->getHeaderLine('Location'));

        // Verifica no banco de dados
        $stmt = $this->pdo->query('SELECT last_verified_at, verified_by_user_id FROM owners WHERE id = 10');
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($owner['last_verified_at']);
        $this->assertSame(2, (int)$owner['verified_by_user_id']);

        // Verifica se auditoria foi gravada
        $stmtAudit = $this->pdo->query("SELECT action, user_id FROM audit_logs WHERE entity_type = 'owner' AND entity_id = '10'");
        $audit = $stmtAudit->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($audit);
        $this->assertSame('owner.verified', $audit['action']);
        $this->assertSame(2, (int)$audit['user_id']);

        // Agora na página do proprietário, deve constar como atualizado
        $reqShow = (new ServerRequestFactory())->createServerRequest('GET', '/owners/10');
        $respShow = $this->app->handle($reqShow);
        $bodyShow = (string)$respShow->getBody();

        $this->assertStringContainsString('Cadastro Atualizado', $bodyShow);
        $this->assertStringContainsString('Válido por mais', $bodyShow);
    }
}
