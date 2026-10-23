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

final class LeadManagementTest extends TestCase
{
    private string $tempDb;
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_leads_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();

        // Cria usuários de teste: Admin e Corretor
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
        $_SESSION['user_email'] = strtolower($name) . '@pinciara.com';
        $_SESSION['user_role'] = $role;
        $_SESSION['user_role_label'] = $roleLabel;
    }

    public function testCorretorCanCreateLeadWithValidDataAndNormalization(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $token = Csrf::getToken();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/leads')
            ->withParsedBody([
                '_csrf_token' => $token,
                'name' => '  Fernanda Lima  ',
                'email' => '  Fernanda.Lima@Gmail.COM  ',
                'phone' => '+55 (21) 98765-4321',
                'source' => 'whatsapp',
                'notes' => 'Interessada em apartamento na praia de Icaraí.',
            ]);

        $response = $this->app->handle($request);

        // Deve redirecionar para a ficha do lead recém-criado
        $this->assertSame(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('#^/leads/[0-9]+$#', $location);

        // Verifica normalização no banco de dados
        $lead = $this->pdo->query("SELECT * FROM leads WHERE name = 'Fernanda Lima'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($lead);
        $this->assertSame('fernanda.lima@gmail.com', $lead['email']);
        $this->assertSame('21987654321', $lead['phone']);
        $this->assertSame('novo', $lead['stage']);
        $this->assertSame(2, (int)$lead['user_id']); // atribuído ao autor

        // Verifica auditoria
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'lead.create' AND entity_id = '{$lead['id']}'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($audit);
        $this->assertSame(2, (int)$audit['user_id']);
    }

    public function testCreateLeadFailsWhenNameIsMissingEvenIfOptionalContactsAreEmpty(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $token = Csrf::getToken();

        // Envia sem nome e sem meio de contato
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/leads')
            ->withParsedBody([
                '_csrf_token' => $token,
                'name' => '',
                'email' => '',
                'phone' => '',
            ]);

        $response = $this->app->handle($request);

        $this->assertSame(422, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('O nome do lead é obrigatório', $body);
        $this->assertStringNotContainsString('Informe ao menos um meio de contato', $body);
    }

    public function testCreateLeadDetectsDuplicateEmailAndPhone(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $token = Csrf::getToken();

        // Insere lead inicial
        $this->pdo->exec("
            INSERT INTO leads (name, email, phone, stage)
            VALUES ('Cliente Existente', 'duplicado@pinciara.com', '21999990000', 'novo')
        ");

        // Tenta cadastrar outro lead com o mesmo e-mail
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/leads')
            ->withParsedBody([
                '_csrf_token' => $token,
                'name' => 'Novo Tentativa',
                'email' => 'duplicado@pinciara.com',
                'phone' => '21988881111',
            ]);

        $response = $this->app->handle($request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Já existe um lead cadastrado com o e-mail', (string)$response->getBody());
    }

    public function testCorretorCanAdvanceLeadPipelineStage(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        // Cria lead no estágio inicial 'novo'
        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage)
            VALUES (10, 'Lead Pipeline Teste', '21988887777', 'novo')
        ");

        $token = Csrf::getToken();

        // 1. Transição permitida: novo -> contato_realizado
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/leads/10/stage')
            ->withParsedBody([
                '_csrf_token' => $token,
                'stage' => 'contato_realizado',
            ]);

        $response = $this->app->handle($request);
        $this->assertSame(302, $response->getStatusCode());

        $lead = $this->pdo->query('SELECT stage FROM leads WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('contato_realizado', $lead['stage']);

        // Verifica auditoria da mudança de estágio
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'lead.stage_change' AND entity_id = '10'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($audit);
        $this->assertStringContainsString('contato_realizado', $audit['new_values']);
    }

    public function testPipelineTransitionToPerdidoRequiresMandatoryLossReason(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage)
            VALUES (15, 'Lead Perda Teste', '21988886666', 'novo')
        ");

        $token = Csrf::getToken();

        // 1. Tenta transitar para 'perdido' SEM motivo de perda -> deve falhar
        $requestWithoutReason = (new ServerRequestFactory())->createServerRequest('POST', '/leads/15/stage')
            ->withParsedBody([
                '_csrf_token' => $token,
                'stage' => 'perdido',
                'loss_reason' => '',
            ]);

        $responseWithoutReason = $this->app->handle($requestWithoutReason);
        $this->assertSame(302, $responseWithoutReason->getStatusCode());

        // Estágio continua sendo 'novo'
        $lead = $this->pdo->query('SELECT stage, loss_reason FROM leads WHERE id = 15')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('novo', $lead['stage']);

        // 2. Transita para 'perdido' COM motivo válido -> deve suceder
        $requestWithReason = (new ServerRequestFactory())->createServerRequest('POST', '/leads/15/stage')
            ->withParsedBody([
                '_csrf_token' => $token,
                'stage' => 'perdido',
                'loss_reason' => 'Cliente fechou compra com outra imobiliária no mesmo condomínio.',
            ]);

        $responseWithReason = $this->app->handle($requestWithReason);
        $this->assertSame(302, $responseWithReason->getStatusCode());

        $leadUpdated = $this->pdo->query('SELECT stage, loss_reason FROM leads WHERE id = 15')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('perdido', $leadUpdated['stage']);
        $this->assertSame('Cliente fechou compra com outra imobiliária no mesmo condomínio.', $leadUpdated['loss_reason']);
    }

    public function testReassignLeadRequiresReasonAndRecordsAuditLog(): void
    {
        $this->authenticateAs(1, 'Gestor Imobiliária', 'admin', 'Administrador');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage, user_id)
            VALUES (20, 'Lead Reatribuicao', '21977775555', 'qualificacao', 2)
        ");

        $token = Csrf::getToken();

        // Transfere do usuário 2 (Carlos) para o usuário 3 (Mariana)
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/leads/20/reassign')
            ->withParsedBody([
                '_csrf_token' => $token,
                'to_user_id' => 3,
                'reason' => 'Mariana é especialista em imóveis na Região Oceânica.',
            ]);

        $response = $this->app->handle($request);
        $this->assertSame(302, $response->getStatusCode());

        // Verifica que o lead agora pertence à usuária 3
        $lead = $this->pdo->query('SELECT user_id FROM leads WHERE id = 20')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(3, (int)$lead['user_id']);

        // Verifica registro na tabela dedicada de histórico de atribuições
        $assignment = $this->pdo->query('SELECT * FROM lead_assignments WHERE lead_id = 20')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($assignment);
        $this->assertSame(2, (int)$assignment['from_user_id']);
        $this->assertSame(3, (int)$assignment['to_user_id']);
        $this->assertSame(1, (int)$assignment['assigned_by_user_id']);
        $this->assertSame('Mariana é especialista em imóveis na Região Oceânica.', $assignment['reason']);

        // Verifica na trilha global de auditoria
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'lead.reassigned' AND entity_id = '20'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($audit);
    }

    public function testNToNAssociationBetweenLeadsAndProperties(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage)
            VALUES (30, 'Lead Imovel N:N', '21966664444', 'visita_agendada')
        ");

        $token = Csrf::getToken();

        // 1. Associa o imóvel 1 (AP101) ao lead 30
        $requestAssociate = (new ServerRequestFactory())->createServerRequest('POST', '/leads/30/properties')
            ->withParsedBody([
                '_csrf_token' => $token,
                'property_id' => 1,
                'interest_type' => 'visita_agendada',
                'notes' => 'Visita marcada para sábado às 10h.',
            ]);

        $responseAssociate = $this->app->handle($requestAssociate);
        $this->assertSame(302, $responseAssociate->getStatusCode());

        // Verifica registro na tabela pivot lead_properties
        $pivot = $this->pdo->query('SELECT * FROM lead_properties WHERE lead_id = 30 AND property_id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($pivot);
        $this->assertSame('visita_agendada', $pivot['interest_type']);
        $this->assertSame('Visita marcada para sábado às 10h.', $pivot['notes']);

        // 2. Desassocia o imóvel
        $requestDisassociate = (new ServerRequestFactory())->createServerRequest('POST', '/leads/30/properties/1/delete')
            ->withParsedBody(['_csrf_token' => $token]);

        $responseDisassociate = $this->app->handle($requestDisassociate);
        $this->assertSame(302, $responseDisassociate->getStatusCode());

        $pivotAfter = $this->pdo->query('SELECT * FROM lead_properties WHERE lead_id = 30 AND property_id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEmpty($pivotAfter);
    }

    public function testSearchFiltersAndPaginationOnServerSide(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        // Insere vários leads para testar paginação e filtros
        $this->pdo->exec("
            INSERT INTO leads (name, email, phone, stage, user_id, source) VALUES
            ('Alice Morais', 'alice@gmail.com', '21911111111', 'novo', 2, 'whatsapp'),
            ('Bruno Castro', 'bruno@gmail.com', '21922222222', 'qualificacao', 3, 'site'),
            ('Camila Dias', 'camila@gmail.com', '21933333333', 'novo', 2, 'portal'),
            ('Daniel Ramos', 'daniel@gmail.com', '21944444444', 'negociacao', 2, 'placa');
        ");

        // 1. Busca por termo de texto
        $reqSearch = (new ServerRequestFactory())->createServerRequest('GET', '/leads?q=Alice');
        $resSearch = $this->app->handle($reqSearch);
        $this->assertSame(200, $resSearch->getStatusCode());
        $bodySearch = (string)$resSearch->getBody();
        $this->assertStringContainsString('Alice Morais', $bodySearch);
        $this->assertStringNotContainsString('Bruno Castro', $bodySearch);

        // 2. Filtro por estágio
        $reqStage = (new ServerRequestFactory())->createServerRequest('GET', '/leads?stage=negociacao');
        $resStage = $this->app->handle($reqStage);
        $this->assertSame(200, $resStage->getStatusCode());
        $bodyStage = (string)$resStage->getBody();
        $this->assertStringContainsString('Daniel Ramos', $bodyStage);
        $this->assertStringNotContainsString('Camila Dias', $bodyStage);

        // 3. Paginação no servidor (limite de 2 por página)
        $reqPage = (new ServerRequestFactory())->createServerRequest('GET', '/leads?page=1&per_page=2');
        $resPage = $this->app->handle($reqPage);
        $this->assertSame(200, $resPage->getStatusCode());
        $this->assertStringContainsString('Mostrando <strong>1</strong> a <strong>2</strong> de <strong>4</strong> leads', (string)$resPage->getBody());

        // 4. Suporte a HTMX (retorna apenas o fragmento parcial sem cabeçalho)
        $reqHtmx = (new ServerRequestFactory())->createServerRequest('GET', '/leads?q=Bruno')
            ->withHeader('HX-Request', 'true');
        $resHtmx = $this->app->handle($reqHtmx);
        $this->assertSame(200, $resHtmx->getStatusCode());
        $bodyHtmx = (string)$resHtmx->getBody();
        $this->assertStringContainsString('Bruno Castro', $bodyHtmx);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $bodyHtmx); // apenas o partial
    }

    public function testCorretorCannotDeleteLeadWhileAdminCanDeleteLead(): void
    {
        $this->pdo->exec("
            INSERT INTO leads (id, name, phone, stage)
            VALUES (50, 'Lead Para Exclusao', '21900000000', 'novo')
        ");

        $token = Csrf::getToken();

        // 1. Corretor tenta excluir -> deve receber 403 Proibido
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');
        $reqCorretor = (new ServerRequestFactory())->createServerRequest('POST', '/leads/50/delete')
            ->withParsedBody(['_csrf_token' => $token]);

        $resCorretor = $this->app->handle($reqCorretor);
        $this->assertSame(403, $resCorretor->getStatusCode());
        $this->assertStringContainsString('Acesso Proibido', (string)$resCorretor->getBody());

        // Lead ainda existe no banco
        $lead = $this->pdo->query('SELECT id FROM leads WHERE id = 50')->fetch();
        $this->assertNotEmpty($lead);

        // 2. Administrador tenta excluir -> deve suceder com 302
        $this->authenticateAs(1, 'Gestor Imobiliária', 'admin', 'Administrador');
        $reqAdmin = (new ServerRequestFactory())->createServerRequest('POST', '/leads/50/delete')
            ->withParsedBody(['_csrf_token' => $token]);

        $resAdmin = $this->app->handle($reqAdmin);
        $this->assertSame(302, $resAdmin->getStatusCode());
        $this->assertSame('/leads', $resAdmin->getHeaderLine('Location'));

        // Lead foi removido do banco
        $leadDeleted = $this->pdo->query('SELECT id FROM leads WHERE id = 50')->fetch();
        $this->assertEmpty($leadDeleted);

        // Auditoria registrou a exclusão
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'lead.delete' AND entity_id = '50'")->fetch();
        $this->assertNotEmpty($audit);
    }
}
