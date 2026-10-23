<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database\Connection;
use App\Database\Migrator;
use App\Modules\Leads\Repositories\FollowUpRepository;
use App\Modules\Leads\Repositories\InteractionRepository;
use App\Modules\Leads\Repositories\LeadRepository;
use App\Modules\Leads\Services\FollowUpService;
use App\Support\DateHelper;
use PDO;
use PHPUnit\Framework\TestCase;

final class FollowUpServiceTest extends TestCase
{
    private string $tempDb;
    private PDO $pdo;
    private FollowUpService $service;
    private LeadRepository $leadRepo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_follow_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();
        DateHelper::setTimezone('America/Sao_Paulo');

        // Cria usuários de teste: Admin (1) e dois corretores (2 e 3)
        $this->pdo->exec("
            INSERT INTO users (id, name, email, password_hash, role_id, is_active) VALUES
            (1, 'Admin Geral', 'admin@pinciara.com', 'hash', 1, 1),
            (2, 'Bruno Corretor', 'bruno@pinciara.com', 'hash', 2, 1),
            (3, 'Camila Corretora', 'camila@pinciara.com', 'hash', 2, 1);
        ");

        $this->leadRepo = new LeadRepository($this->pdo);
        $followUpRepo = new FollowUpRepository($this->pdo);
        $interRepo = new InteractionRepository($this->pdo);
        $this->service = new FollowUpService($followUpRepo, $this->leadRepo, $interRepo);
    }

    protected function tearDown(): void
    {
        DateHelper::resetTimezone();
        Connection::reset();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function testCanScheduleFollowUpAndSyncLeadNextFollowUp(): void
    {
        $leadId = $this->leadRepo->create([
            'name' => 'Juliana Mendes',
            'phone' => '21988889999',
            'user_id' => 2,
        ]);

        $scheduledDate = DateHelper::now()->modify('+2 days')->format('Y-m-d\TH:i');

        $result = $this->service->scheduleFollowUp($leadId, [
            'scheduled_at' => $scheduledDate,
            'user_id' => 2,
            'notes' => 'Apresentar propostas de financiamento.',
        ], 2);

        $this->assertTrue($result['success']);
        $followUpId = $result['follow_up_id'];

        // Verifica sincronização no lead
        $lead = $this->leadRepo->findById($leadId);
        $this->assertNotEmpty($lead['next_follow_up_at']);
        $this->assertSame(DateHelper::fromHtmlDatetimeLocal($scheduledDate), $lead['next_follow_up_at']);

        // Verifica auditoria
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'lead.follow_up_scheduled' AND entity_id = '{$followUpId}'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($audit);
    }

    public function testCanCompleteFollowUpAndRecordInteractionTimeline(): void
    {
        $leadId = $this->leadRepo->create([
            'name' => 'Marcos Vinicius',
            'phone' => '21977778888',
            'user_id' => 2,
        ]);

        $resSchedule = $this->service->scheduleFollowUp($leadId, [
            'scheduled_at' => DateHelper::now()->modify('-1 hour')->format('Y-m-d\TH:i'), // Atrasado
            'user_id' => 2,
            'notes' => 'Confirmar horário da visita.',
        ], 2);

        $followUpId = $resSchedule['follow_up_id'];

        // Conclui o follow-up registrando o resultado do contato
        $completeResult = $this->service->completeFollowUp($followUpId, [
            'type' => 'whatsapp',
            'result_notes' => 'Cliente confirmou presença na visita amanhã às 14h com o cônjuge.',
        ], 2);

        $this->assertTrue($completeResult['success']);

        // Verifica se o follow-up foi marcado como concluído
        $followUp = $this->service->getRepository()->findById($followUpId);
        $this->assertSame('completed', $followUp['status']);
        $this->assertSame('Cliente confirmou presença na visita amanhã às 14h com o cônjuge.', $followUp['result_notes']);
        $this->assertNotEmpty($followUp['completed_at']);

        // Verifica se gerou o registro no histórico cronológico de interações
        $interactions = $this->pdo->query("SELECT * FROM lead_interactions WHERE lead_id = {$leadId}")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $interactions);
        $this->assertSame('whatsapp', $interactions[0]['type']);
        $this->assertStringContainsString('Cliente confirmou presença', $interactions[0]['notes']);

        // Como não foi agendado próximo retorno, leads.next_follow_up_at deve ter sido limpo para null
        $lead = $this->leadRepo->findById($leadId);
        $this->assertNull($lead['next_follow_up_at']);
    }

    public function testCompleteFollowUpCanScheduleNextFollowUpInSameStep(): void
    {
        $leadId = $this->leadRepo->create([
            'name' => 'Carla Diaz',
            'phone' => '21966667777',
            'user_id' => 2,
        ]);

        $resSchedule = $this->service->scheduleFollowUp($leadId, [
            'scheduled_at' => DateHelper::now()->modify('-2 hours')->format('Y-m-d\TH:i'),
            'user_id' => 2,
            'notes' => 'Primeiro contato.',
        ], 2);

        $followUpId = $resSchedule['follow_up_id'];

        $nextDate = DateHelper::now()->modify('+3 days')->format('Y-m-d\TH:i');

        // Conclui e já agenda o próximo
        $resComplete = $this->service->completeFollowUp($followUpId, [
            'type' => 'ligacao',
            'result_notes' => 'Cliente atendeu. Gostou da localização, pediu retorno na quinta-feira.',
            'schedule_next' => '1',
            'next_scheduled_at' => $nextDate,
            'next_user_id' => 2,
            'next_notes' => 'Enviar opções de 3 quartos.',
        ], 2);

        $this->assertTrue($resComplete['success']);

        // Verifica que o lead agora tem o novo next_follow_up_at agendado
        $lead = $this->leadRepo->findById($leadId);
        $this->assertNotNull($lead['next_follow_up_at']);
        $this->assertSame(DateHelper::fromHtmlDatetimeLocal($nextDate), $lead['next_follow_up_at']);

        // Verifica que temos 2 follow-ups (1 concluído, 1 pendente)
        $all = $this->service->getRepository()->getAllForLead($leadId);
        $this->assertCount(2, $all);
    }

    public function testCanRescheduleFollowUpWithAuditTrail(): void
    {
        $leadId = $this->leadRepo->create([
            'name' => 'Felipe Neto',
            'phone' => '21955554444',
            'user_id' => 2,
        ]);

        $resSchedule = $this->service->scheduleFollowUp($leadId, [
            'scheduled_at' => DateHelper::now()->format('Y-m-d\TH:i'),
            'user_id' => 2,
            'notes' => 'Retorno de rotina.',
        ], 2);

        $followUpId = $resSchedule['follow_up_id'];

        $newDate = DateHelper::now()->modify('+1 day')->format('Y-m-d\TH:i');

        $rescheduleResult = $this->service->rescheduleFollowUp($followUpId, [
            'scheduled_at' => $newDate,
            'user_id' => 3, // transfere para Camila
            'reason' => 'Cliente solicitou contato apenas amanhã de manhã.',
        ], 2);

        $this->assertTrue($rescheduleResult['success']);

        $followUp = $this->service->getRepository()->findById($followUpId);
        $this->assertSame(3, (int)$followUp['user_id']); // Atualizou responsável
        $this->assertSame(DateHelper::fromHtmlDatetimeLocal($newDate), $followUp['scheduled_at']);
        $this->assertStringContainsString('Reagendado por', $followUp['notes']);

        // Verifica auditoria
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'lead.follow_up_rescheduled' AND entity_id = '{$followUpId}'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($audit);
    }

    public function testDashboardViewDataRespectsBrokerRolePermissions(): void
    {
        $lead1 = $this->leadRepo->create(['name' => 'Lead do Bruno', 'user_id' => 2]);
        $lead2 = $this->leadRepo->create(['name' => 'Lead da Camila', 'user_id' => 3]);

        $todayStr = DateHelper::now()->format('Y-m-d\TH:i');

        // Follow-up para Bruno
        $this->service->scheduleFollowUp($lead1, [
            'scheduled_at' => $todayStr,
            'user_id' => 2,
        ], 2);

        // Follow-up para Camila
        $this->service->scheduleFollowUp($lead2, [
            'scheduled_at' => $todayStr,
            'user_id' => 3,
        ], 3);

        $currentUserCorretor = [
            'id' => 2,
            'name' => 'Bruno Corretor',
            'role' => 'corretor',
            'role_label' => 'Corretor',
        ];

        // 1. Corretor sem filtro explícito vê por padrão apenas os seus
        $dataDefault = $this->service->getDashboardViewData([], $currentUserCorretor);
        $this->assertCount(1, $dataDefault['followUps']);
        $this->assertSame('Lead do Bruno', $dataDefault['followUps'][0]['lead_name']);

        // 2. Corretor escolhendo "all" vê de toda a equipe (acesso colaborativo)
        $dataAll = $this->service->getDashboardViewData(['user_id' => 'all'], $currentUserCorretor);
        $this->assertCount(2, $dataAll['followUps']);

        // 3. Admin vê de todos por padrão
        $currentUserAdmin = [
            'id' => 1,
            'name' => 'Admin Geral',
            'role' => 'admin',
            'role_label' => 'Administrador',
        ];

        $dataAdmin = $this->service->getDashboardViewData([], $currentUserAdmin);
        $this->assertCount(2, $dataAdmin['followUps']);
    }
}
