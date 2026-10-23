<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Application;
use App\Auth\Session\DatabaseSessionHandler;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Database\Migrator;
use App\Security\Csrf;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\UriFactory;

final class RouteAuthorizationTest extends TestCase
{
    private string $tempDb;
    private App $app;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_routes_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

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

    public function testHealthCheckReturnsOk(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('"status": "ok"', $body);
        $this->assertStringContainsString('"database": "connected"', $body);
    }

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $routes = ['/', '/leads', '/properties', '/signboards', '/admin/users', '/admin/audit'];

        foreach ($routes as $route) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', $route);
            $response = $this->app->handle($request);

            $this->assertSame(302, $response->getStatusCode(), "Rota {$route} deveria redirecionar usuário não autenticado.");
            $this->assertSame('/login', $response->getHeaderLine('Location'));
        }
    }

    public function testCorretorCanAccessLeadsButIsForbiddenFromAdminRoutes(): void
    {
        // Simula sessão de corretor
        $_SESSION['user_id'] = 2;
        $_SESSION['user_name'] = 'Marcos Corretor';
        $_SESSION['user_email'] = 'marcos@pinciara.com';
        $_SESSION['user_role'] = 'corretor';
        $_SESSION['user_role_label'] = 'Corretor';

        // 1. Acesso a Leads -> 200 OK
        $requestLeads = (new ServerRequestFactory())->createServerRequest('GET', '/leads');
        $responseLeads = $this->app->handle($requestLeads);
        $this->assertSame(200, $responseLeads->getStatusCode());
        $this->assertStringContainsString('Módulo de Leads', (string)$responseLeads->getBody());

        // 2. Acesso à área administrativa -> 403 Forbidden
        $requestAdminUsers = (new ServerRequestFactory())->createServerRequest('GET', '/admin/users');
        $responseAdminUsers = $this->app->handle($requestAdminUsers);
        $this->assertSame(403, $responseAdminUsers->getStatusCode());
        $this->assertStringContainsString('403', (string)$responseAdminUsers->getBody());

        $requestAdminAudit = (new ServerRequestFactory())->createServerRequest('GET', '/admin/audit');
        $responseAdminAudit = $this->app->handle($requestAdminAudit);
        $this->assertSame(403, $responseAdminAudit->getStatusCode());
    }

    public function testAdminCanAccessAdminRoutes(): void
    {
        // Simula sessão de admin
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Admin Gestor';
        $_SESSION['user_email'] = 'admin@pinciara.com';
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_role_label'] = 'Administrador';

        $requestUsers = (new ServerRequestFactory())->createServerRequest('GET', '/admin/users');
        $responseUsers = $this->app->handle($requestUsers);
        $this->assertSame(200, $responseUsers->getStatusCode());
        $this->assertStringContainsString('Gestão de Usuários', (string)$responseUsers->getBody());

        $requestAudit = (new ServerRequestFactory())->createServerRequest('GET', '/admin/audit');
        $responseAudit = $this->app->handle($requestAudit);
        $this->assertSame(200, $responseAudit->getStatusCode());
        $this->assertStringContainsString('Trilha de Auditoria', (string)$responseAudit->getBody());
    }

    public function testChangingRoleInvalidatesExistingSessionsForThatUser(): void
    {
        $pdo = Connection::getPdo();
        $pdo->exec("INSERT INTO users (name, email, password_hash, role_id) VALUES ('Admin Gestor', 'admin@pinciara.com', 'hash', 1)");
        $actorId = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO users (name, email, password_hash, role_id) VALUES ('Admin alvo', 'alvo@pinciara.com', 'hash', 1)");
        $targetId = (int)$pdo->lastInsertId();

        $targetSessionId = 'existing-target-session';
        $pdo->prepare('INSERT INTO sessions (id, user_id, payload, last_activity) VALUES (:id, :user_id, :payload, :last_activity)')
            ->execute([
                ':id' => $targetSessionId,
                ':user_id' => $targetId,
                ':payload' => serialize(['user_id' => $targetId, 'user_role' => 'admin']),
                ':last_activity' => time(),
            ]);

        $_SESSION['user_id'] = $actorId;
        $_SESSION['user_name'] = 'Admin Gestor';
        $_SESSION['user_email'] = 'admin@pinciara.com';
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_role_label'] = 'Administrador';
        $token = Csrf::getToken();

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', "/admin/users/{$targetId}/role")
            ->withParsedBody(['_csrf_token' => $token, 'role_id' => 2]);
        $response = $this->app->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM sessions WHERE id = '{$targetSessionId}'")->fetchColumn());
        $this->assertSame('', (new DatabaseSessionHandler())->read($targetSessionId));
    }

    public function testMutatingRequestWithoutCsrfTokenIsRejected(): void
    {
        // Requisição POST via HTMX sem token CSRF
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/login')
            ->withHeader('HX-Request', 'true');

        $response = $this->app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Token CSRF inválido', (string)$response->getBody());
    }

    public function testMutatingRequestWithValidCsrfTokenPassesCsrfCheck(): void
    {
        $token = Csrf::getToken();

        // Requisição POST com token CSRF válido
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/login')
            ->withParsedBody([
                '_csrf_token' => $token,
                'email' => 'naoexiste@pinciara.com',
                'password' => 'qualquer',
            ]);

        $response = $this->app->handle($request);

        // Não é 403 (ou seja, passou pela barreira do CSRF e chegou no controller de auth que redireciona de volta após credenciais inválidas)
        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }
}
