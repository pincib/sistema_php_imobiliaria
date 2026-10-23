<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Audit\AuditService;
use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Database\Migrator;
use App\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthSecurityTest extends TestCase
{
    private string $tempDb;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_auth_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        // Inicia e limpa sessão para o teste
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

    public function testLoginSucceedsWithValidCredentialsAndLogsAudit(): void
    {
        $pdo = Connection::getPdo();
        $hash = password_hash('SenhaSegura123!', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role_id, is_active)
            VALUES (:name, :email, :password_hash, 1, 1)
        ');
        $stmt->execute([
            ':name' => 'Carlos Administrador',
            ':email' => 'carlos@pinciara.com',
            ':password_hash' => $hash,
        ]);

        $result = AuthService::attempt('carlos@pinciara.com', 'SenhaSegura123!');
        $this->assertTrue($result['success']);
        $this->assertTrue(AuthService::check());

        $user = AuthService::user();
        $this->assertNotNull($user);
        $this->assertSame('Carlos Administrador', $user['name']);
        $this->assertSame('admin', $user['role']);

        // Verifica log de auditoria
        $audit = $pdo->query("SELECT action, user_id FROM audit_logs WHERE action = 'auth.login'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($audit);
        $this->assertSame('auth.login', $audit['action']);
    }

    public function testLoginFailsWithWrongPasswordAndRecordsAudit(): void
    {
        $pdo = Connection::getPdo();
        $hash = password_hash('SenhaCorreta123', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role_id, is_active)
            VALUES (:name, :email, :password_hash, 2, 1)
        ');
        $stmt->execute([
            ':name' => 'Marcos Corretor',
            ':email' => 'marcos@pinciara.com',
            ':password_hash' => $hash,
        ]);

        $result = AuthService::attempt('marcos@pinciara.com', 'SenhaErrada');
        $this->assertFalse($result['success']);
        $this->assertFalse(AuthService::check());
        $this->assertSame('E-mail ou senha incorretos.', $result['error']);

        // Verifica log de auditoria do erro
        $audit = $pdo->query("SELECT action, new_values FROM audit_logs WHERE action = 'auth.failed_login'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($audit);
        $this->assertStringContainsString('marcos@pinciara.com', (string)$audit['new_values']);
    }

    public function testInactiveUserCannotLogin(): void
    {
        $pdo = Connection::getPdo();
        $hash = password_hash('SenhaCorreta123', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role_id, is_active)
            VALUES (:name, :email, :password_hash, 2, 0)
        ');
        $stmt->execute([
            ':name' => 'Usuario Desativado',
            ':email' => 'inativo@pinciara.com',
            ':password_hash' => $hash,
        ]);

        $result = AuthService::attempt('inativo@pinciara.com', 'SenhaCorreta123');
        $this->assertFalse($result['success']);
        $this->assertFalse(AuthService::check());
        $this->assertStringContainsString('desativada', $result['error']);
    }

    public function testCsrfTokenGenerationAndConstantTimeValidation(): void
    {
        $token = Csrf::getToken();
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes hex

        $this->assertTrue(Csrf::validate($token));
        $this->assertFalse(Csrf::validate('token_invalido_ou_forjado'));
        $this->assertFalse(Csrf::validate(null));
    }

    public function testFailedLoginPreservesEmailInSession(): void
    {
        $authController = new \App\Modules\Auth\Controllers\AuthController();
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', '/login')
            ->withParsedBody([
                'email' => 'corretor.plantao@pinciara.com',
                'password' => 'SenhaErrada123',
            ]);
        $response = new \Slim\Psr7\Response();

        $resp = $authController->login($request, $response);
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame('corretor.plantao@pinciara.com', $_SESSION['_old_email'] ?? null);

        // Ao carregar a tela de login, o e-mail é consumido e exibido
        $reqGet = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/login');
        $respGet = $authController->showLoginForm($reqGet, new \Slim\Psr7\Response());
        $body = (string)$respGet->getBody();

        $this->assertStringContainsString('value="corretor.plantao@pinciara.com"', $body);
        $this->assertArrayNotHasKey('_old_email', $_SESSION);
    }
}
