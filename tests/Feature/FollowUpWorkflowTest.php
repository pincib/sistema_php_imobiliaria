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

final class FollowUpWorkflowTest extends TestCase
{
    private string $tempDb;
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_follow_flow_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();
        DateHelper::setTimezone('America/Sao_Paulo');

        // Cria usuários de teste
        $hash = password_hash('SenhaSegura123!', PASSWORD_DEFAULT);
        $this->pdo->exec("
            INSERT INTO users (id, name, email, password_hash, role_id, is_active) VALUES
            (1, 'Gestor Imobiliária', 'gestor@pinciara.com', '{$hash}', 1, 1),
            (2, 'Carlos Corretor', 'carlos@pinciara.com', '{$hash}', 2, 1),
            (3, 'Mariana Corretora', 'mariana@pinciara.com', '{$hash}', 2, 1);
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

    private function authenticateAs(int $userId, string $name, string $role, string $roleLabel): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = strtolower(str_replace(' ', '.', $name)) . '@pinciara.com';
        $_SESSION['user_role'] = $role;
        $_SESSION['user_role_label'] = $roleLabel;
    }

    public function testCorretorSeesDashboardWithCategorizedFollowUps(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        $now = DateHelper::now();
        $overdueAt = $now->modify('-3 hours')->format('Y-m-d H:i:s');
        $todayAt = $now->modify('+2 hours')->format('Y-m-d H:i:s');
        $futureAt = $now->modify('+3 days')->format('Y-m-d H:i:s');

        // Cria 3 leads com follow-ups
        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage, user_id, next_follow_up_at) VALUES
            (1, 'Lead Atrasado', '21988880001', 'novo', 2, '{$overdueAt}'),
            (2, 'Lead de Hoje', '21988880002', 'qualificacao', 2, '{$todayAt}'),
            (3, 'Lead Futuro', '21988880003', 'visita_agendada', 2, '{$futureAt}');

            INSERT INTO lead_follow_ups (id, lead_id, user_id, created_by_user_id, scheduled_at, status, notes) VALUES
            (1, 1, 2, 2, '{$overdueAt}', 'pending', 'Ligar com urgência para não esfriar'),
            (2, 2, 2, 2, '{$todayAt}', 'pending', 'Confirmar se recebeu as fotos'),
            (3, 3, 2, 2, '{$futureAt}', 'pending', 'Retornar após reunião de diretoria');
        ");

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();

        // Verifica presença dos títulos e indicadores
        $this->assertStringContainsString('Agenda Operacional de Follow-ups', $body);
        $this->assertStringContainsString('Retornos Atrasados', $body);
        $this->assertStringContainsString('Contatos de Hoje', $body);
        $this->assertStringContainsString('Retornos Futuros', $body);

        // Verifica dados do lead no painel
        $this->assertStringContainsString('Lead de Hoje', $body);
    }

    public function testUserCanFilterFollowUpsBySituationAndHTMXPartial(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        $now = DateHelper::now();
        $overdueAt = $now->modify('-2 hours')->format('Y-m-d H:i:s');
        $todayAt = $now->modify('+1 hour')->format('Y-m-d H:i:s');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage, user_id, next_follow_up_at) VALUES
            (1, 'Cliente Vencido', '21988881111', 'novo', 2, '{$overdueAt}'),
            (2, 'Cliente Hoje', '21988882222', 'novo', 2, '{$todayAt}');

            INSERT INTO lead_follow_ups (id, lead_id, user_id, created_by_user_id, scheduled_at, status, notes) VALUES
            (1, 1, 2, 2, '{$overdueAt}', 'pending', 'Atrasadíssimo'),
            (2, 2, 2, 2, '{$todayAt}', 'pending', 'Para logo mais');
        ");

        // Requisição HTMX filtrando por situation=overdue
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/?situation=overdue&partial=follow-ups')
            ->withHeader('HX-Request', 'true');

        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $html = (string)$response->getBody();

        $this->assertStringContainsString('Cliente Vencido', $html);
        $this->assertStringNotContainsString('Cliente Hoje', $html);
    }

    public function testUserCanLocateOverdueFollowUpAndRecordContactOutcome(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $token = Csrf::getToken();

        $overdueAt = DateHelper::now()->modify('-4 hours')->format('Y-m-d H:i:s');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage, user_id, next_follow_up_at) VALUES
            (10, 'Renata Sorrah', '21988883333', 'qualificacao', 2, '{$overdueAt}');

            INSERT INTO lead_follow_ups (id, lead_id, user_id, created_by_user_id, scheduled_at, status, notes) VALUES
            (100, 10, 2, 2, '{$overdueAt}', 'pending', 'Cobrar resposta sobre proposta');
        ");

        $idempotencyKey = 'complete-token-test-100';

        // POST para concluir follow-up registrando o resultado do contato
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/follow-ups/100/complete')
            ->withParsedBody([
                '_csrf_token' => $token,
                'type' => 'ligacao',
                'result_notes' => 'Cliente atendeu. Aceitou a contraproposta do proprietário e pediu a minuta.',
                'contacted_at' => DateHelper::now()->format('Y-m-d\TH:i'),
                'idempotency_key' => $idempotencyKey,
            ]);

        $response = $this->app->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->getHeaderLine('Location'));

        // Verifica se o follow-up foi concluído
        $followUp = $this->pdo->query('SELECT * FROM lead_follow_ups WHERE id = 100')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('completed', $followUp['status']);
        $this->assertStringContainsString('Aceitou a contraproposta', $followUp['result_notes']);
        $this->assertSame(2, (int)$followUp['completed_by_user_id']);

        // Verifica se foi registrado no histórico cronológico de interações do lead
        $interaction = $this->pdo->query('SELECT * FROM lead_interactions WHERE lead_id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($interaction);
        $this->assertSame('ligacao', $interaction['type']);
        $this->assertSame(2, (int)$interaction['user_id']);
        $this->assertStringContainsString('Aceitou a contraproposta', $interaction['notes']);

        // Verifica que leads.next_follow_up_at foi desmarcado pois não há outro pendente
        $lead = $this->pdo->query('SELECT * FROM leads WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($lead['next_follow_up_at']);
    }

    public function testIdempotencyPreventsDuplicateOutcomeRegistration(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $token = Csrf::getToken();

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage, user_id) VALUES (20, 'Bruno Gagliasso', '21988884444', 'novo', 2);
            INSERT INTO lead_follow_ups (id, lead_id, user_id, created_by_user_id, scheduled_at, status) VALUES
            (200, 20, 2, 2, '2026-10-23 08:00:00', 'pending');
        ");

        $key = 'single-run-token-' . uniqid();

        // 1ª Execução
        $request1 = (new ServerRequestFactory())->createServerRequest('POST', '/follow-ups/200/complete')
            ->withParsedBody([
                '_csrf_token' => $token,
                'type' => 'whatsapp',
                'result_notes' => 'Mensagem enviada com sucesso.',
                'idempotency_key' => $key,
            ]);
        $this->app->handle($request1);

        // 2ª Execução com a mesma chave (simulando duplo clique)
        $request2 = (new ServerRequestFactory())->createServerRequest('POST', '/follow-ups/200/complete')
            ->withParsedBody([
                '_csrf_token' => $token,
                'type' => 'whatsapp',
                'result_notes' => 'Mensagem enviada com sucesso.',
                'idempotency_key' => $key,
            ]);
        $this->app->handle($request2);

        // Não deve duplicar a interação no histórico
        $interactions = $this->pdo->query('SELECT * FROM lead_interactions WHERE lead_id = 20')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $interactions);
    }

    public function testReschedulingFollowUpUpdatesDateAndKeepsAudit(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $token = Csrf::getToken();

        $originalDate = DateHelper::now()->format('Y-m-d H:i:s');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage, user_id, next_follow_up_at) VALUES
            (30, 'Luciana Gimenez', '21988885555', 'novo', 2, '{$originalDate}');

            INSERT INTO lead_follow_ups (id, lead_id, user_id, created_by_user_id, scheduled_at, status) VALUES
            (300, 30, 2, 2, '{$originalDate}', 'pending');
        ");

        $newHtmlDate = DateHelper::now()->modify('+2 days 14:00')->format('Y-m-d\TH:i');

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/follow-ups/300/reschedule')
            ->withParsedBody([
                '_csrf_token' => $token,
                'scheduled_at' => $newHtmlDate,
                'user_id' => 3, // transfere para Mariana
                'reason' => 'Cliente pediu para contatar após o feriado.',
            ]);

        $response = $this->app->handle($request);
        $this->assertSame(302, $response->getStatusCode());

        $followUp = $this->pdo->query('SELECT * FROM lead_follow_ups WHERE id = 300')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(3, (int)$followUp['user_id']);
        $this->assertSame(DateHelper::fromHtmlDatetimeLocal($newHtmlDate), $followUp['scheduled_at']);

        // Sincronizou no lead
        $lead = $this->pdo->query('SELECT * FROM leads WHERE id = 30')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(DateHelper::fromHtmlDatetimeLocal($newHtmlDate), $lead['next_follow_up_at']);
    }

    public function testLeadCreationWithFirstFollowUp(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $token = Csrf::getToken();

        $followUpHtml = DateHelper::now()->modify('+1 day 09:30')->format('Y-m-d\TH:i');

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/leads')
            ->withParsedBody([
                '_csrf_token' => $token,
                'name' => 'Fábio Assunção',
                'phone' => '21988886666',
                'source' => 'site',
                'notes' => 'Quer apartamento em Piratininga.',
                'next_follow_up_at' => $followUpHtml,
            ]);

        $response = $this->app->handle($request);
        $this->assertSame(302, $response->getStatusCode());

        $lead = $this->pdo->query("SELECT * FROM leads WHERE name = 'Fábio Assunção'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($lead);
        $this->assertSame(DateHelper::fromHtmlDatetimeLocal($followUpHtml), $lead['next_follow_up_at']);

        // Follow-up correspondente foi criado
        $followUp = $this->pdo->query("SELECT * FROM lead_follow_ups WHERE lead_id = {$lead['id']}")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($followUp);
        $this->assertSame('pending', $followUp['status']);
        $this->assertSame(DateHelper::fromHtmlDatetimeLocal($followUpHtml), $followUp['scheduled_at']);
    }

    public function testLeadDetailViewRendersFollowUpAndInteractionTimeline(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        $scheduledAt = DateHelper::now()->modify('+1 day')->format('Y-m-d H:i:s');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage, user_id, next_follow_up_at) VALUES
            (40, 'Taís Araújo', '21988887777', 'novo', 2, '{$scheduledAt}');

            INSERT INTO lead_follow_ups (id, lead_id, user_id, created_by_user_id, scheduled_at, status, notes) VALUES
            (400, 40, 2, 2, '{$scheduledAt}', 'pending', 'Apresentar lançamento');

            INSERT INTO lead_interactions (id, lead_id, user_id, type, notes, contacted_at) VALUES
            (401, 40, 2, 'whatsapp', 'Conversa inicial sobre plantas de 3 quartos.', '{$scheduledAt}');
        ");

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/leads/40');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $html = (string)$response->getBody();

        // Verifica componentes de follow-up e interações
        $this->assertStringContainsString('Controle de Follow-up (Retorno Programado)', $html);
        $this->assertStringContainsString('Apresentar lançamento', $html);
        $this->assertStringContainsString('Histórico de Interações com o Lead', $html);
        $this->assertStringContainsString('Conversa inicial sobre plantas de 3 quartos.', $html);
    }
}
