<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Application;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Database\Migrator;
use App\Security\Csrf;
use App\Support\DateHelper;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class FollowUpHardeningTest extends TestCase
{
    private string $tempDb;
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_hardening_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();
        DateHelper::setTimezone('America/Sao_Paulo');

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
        DateHelper::resetTimezone();
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

    public function testHardenedInputsAcceptUnicodeEmojisAndLongNotes(): void
    {
        $this->authenticateAs(2, 'Roberto Corretor', 'corretor');

        // Cria lead com nome com acentos e caracteres especiais
        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, email, stage, source, user_id, created_at, updated_at) VALUES
            (10, 'João Álvaro de Bragança & Irmãos Ltda 🏢', '(21) 98765-4321', 'joao.alvaro@dominio.com.br', 'novo', 'site', 2, '2026-10-23 09:00:00', '2026-10-23 09:00:00');
        ");

        // Cria follow-up com emoji e acentos
        $emojiNotes = '📞 Cliente ligou interessado no apartamento com vista mar! 🌊☀️ Solicitar retorno amanhã às 15h. ' . str_repeat('Anotação adicional detalhada. ', 20);
        $this->pdo->exec("
            INSERT INTO lead_follow_ups (id, lead_id, user_id, scheduled_at, status, notes, created_by_user_id, idempotency_key, created_at, updated_at) VALUES
            (1, 10, 2, '2026-10-23 15:00:00', 'pending', " . $this->pdo->quote($emojiNotes) . ", 2, 'key-harden-1', '2026-10-23 09:00:00', '2026-10-23 09:00:00');
        ");

        $csrf = Csrf::getToken();
        $idempotencyKey = 'key-complete-harden-99';

        // Conclui com texto de resultado longo com emojis e caracteres multilíngues
        $resultNotes = "Reunião presencial realizada com sucesso! 🤝 Cliente aprovou orçamento de R$ 1.500.000,00. 💰 " . str_repeat('Observação com caracteres alemães: Übergröße Straße & francês: déjà vu. ', 10);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/follow-ups/1/complete')
            ->withParsedBody([
                '_csrf_token' => $csrf,
                'idempotency_key' => $idempotencyKey,
                'type' => 'reuniao',
                'contacted_at' => '2026-10-23T14:30',
                'result_notes' => $resultNotes,
            ]);

        $response = $this->app->handle($request);
        $this->assertSame(302, $response->getStatusCode());

        // Verifica que foi salvo no banco sem corrupção de caracteres
        $stmt = $this->pdo->query("SELECT status, result_notes FROM lead_follow_ups WHERE id = 1");
        $saved = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('completed', $saved['status']);
        $this->assertStringContainsString('🤝', $saved['result_notes']);
        $this->assertStringContainsString('Übergröße', $saved['result_notes']);

        // Verifica interação gerada
        $stmtInter = $this->pdo->query("SELECT notes FROM lead_interactions WHERE lead_id = 10");
        $inter = $stmtInter->fetch(PDO::FETCH_ASSOC);
        $this->assertStringContainsString('💰', $inter['notes']);
    }

    public function testDashboardPartialRendersHardenedAccessibilityAttributes(): void
    {
        $this->authenticateAs(2, 'Roberto Corretor', 'corretor');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, email, stage, source, user_id, created_at, updated_at) VALUES
            (11, 'Maria Silva', '(21) 99999-8888', 'maria@email.com', 'novo', 'site', 2, '2026-10-23 09:00:00', '2026-10-23 09:00:00');
        ");

        $this->pdo->exec("
            INSERT INTO lead_follow_ups (id, lead_id, user_id, scheduled_at, status, notes, created_by_user_id, idempotency_key, created_at, updated_at) VALUES
            (2, 11, 2, '2026-10-23 16:00:00', 'pending', 'Primeiro contato de apresentação', 2, 'key-harden-2', '2026-10-23 09:00:00', '2026-10-23 09:00:00');
        ");

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/?situation=today&partial=follow-ups');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();

        // Verifica atributos de acessibilidade e resiliência
        $this->assertStringContainsString('aria-current="true"', $body);
        $this->assertStringContainsString('aria-pressed="true"', $body);
        $this->assertStringContainsString('aria-live="polite"', $body);
        $this->assertStringContainsString('id="dashboard-error"', $body);
        $this->assertStringContainsString('aria-modal="true"', $body);
        $this->assertStringContainsString('aria-labelledby="title-dashboard-complete"', $body);
        $this->assertStringContainsString('btn-touch-target', $body);
    }
}
