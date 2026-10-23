<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database\Connection;
use App\Database\Migrator;
use App\Modules\Leads\Repositories\InteractionRepository;
use App\Modules\Leads\Repositories\LeadRepository;
use App\Modules\Leads\Services\InteractionService;
use App\Support\DateHelper;
use PDO;
use PHPUnit\Framework\TestCase;

final class InteractionServiceTest extends TestCase
{
    private string $tempDb;
    private PDO $pdo;
    private InteractionService $service;
    private LeadRepository $leadRepo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_inter_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();
        DateHelper::setTimezone('America/Sao_Paulo');

        // Cria usuários de teste
        $this->pdo->exec("
            INSERT INTO users (id, name, email, password_hash, role_id, is_active) VALUES
            (1, 'Admin Silva', 'admin@pinciara.com', 'hash', 1, 1),
            (2, 'Corretor Santos', 'santos@pinciara.com', 'hash', 2, 1);
        ");

        $this->leadRepo = new LeadRepository($this->pdo);
        $interRepo = new InteractionRepository($this->pdo);
        $this->service = new InteractionService($interRepo, $this->leadRepo);
    }

    protected function tearDown(): void
    {
        DateHelper::resetTimezone();
        Connection::reset();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function testCanCreateInteractionWithChronologicalOrdering(): void
    {
        $leadId = $this->leadRepo->create([
            'name' => 'Roberto Carlos',
            'phone' => '21988887777',
            'email' => 'roberto@example.com',
            'user_id' => 2,
        ]);

        // 1. Cria primeira interação (mais antiga)
        $res1 = $this->service->createInteraction($leadId, [
            'type' => 'ligacao',
            'notes' => 'Primeiro contato por telefone. Cliente atendeu e pediu envio por WhatsApp.',
            'contacted_at' => '2026-10-20T10:00',
        ], 2);

        $this->assertTrue($res1['success']);
        $this->assertNotEmpty($res1['interaction_id']);

        // 2. Cria segunda interação (mais recente)
        $res2 = $this->service->createInteraction($leadId, [
            'type' => 'whatsapp',
            'notes' => 'Enviadas fotos da cobertura duplex em Icaraí.',
            'contacted_at' => '2026-10-22T15:30',
        ], 2);

        $this->assertTrue($res2['success']);

        // 3. Verifica ordenação cronológica (mais recente primeiro)
        $interactions = $this->service->getChronologicalInteractions($leadId);
        $this->assertCount(2, $interactions);
        $this->assertSame('whatsapp', $interactions[0]['type']);
        $this->assertSame('ligacao', $interactions[1]['type']);
        $this->assertSame('Corretor Santos', $interactions[0]['author_name']);

        // 4. Verifica registro de auditoria
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'lead.interaction_created' AND entity_id = '{$res2['interaction_id']}'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($audit);
    }

    public function testValidationFailsForInvalidTypeOrEmptyNotes(): void
    {
        $leadId = $this->leadRepo->create([
            'name' => 'Ana Clara',
            'phone' => '21977776666',
            'user_id' => 2,
        ]);

        // Tipo inválido e notas vazias
        $res = $this->service->createInteraction($leadId, [
            'type' => 'tipo_inexistente',
            'notes' => '   ',
        ], 2);

        $this->assertFalse($res['success']);
        $this->assertArrayHasKey('type', $res['errors']);
        $this->assertArrayHasKey('notes', $res['errors']);
    }

    public function testIdempotencyKeyPreventsDuplicateInteraction(): void
    {
        $leadId = $this->leadRepo->create([
            'name' => 'Eduardo Paes',
            'phone' => '21966665555',
            'user_id' => 2,
        ]);

        $key = 'req-token-' . bin2hex(random_bytes(8));

        // Primeira submissão
        $res1 = $this->service->createInteraction($leadId, [
            'type' => 'ligacao',
            'notes' => 'Ligação atendida.',
            'idempotency_key' => $key,
        ], 2);

        $this->assertTrue($res1['success']);
        $this->assertFalse($res1['duplicate']);

        // Segunda submissão idêntica (duplo clique / refresh de página)
        $res2 = $this->service->createInteraction($leadId, [
            'type' => 'ligacao',
            'notes' => 'Ligação atendida.',
            'idempotency_key' => $key,
        ], 2);

        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['duplicate']);
        $this->assertSame($res1['interaction_id'], $res2['interaction_id']);

        // Garante que só há 1 registro no banco
        $count = $this->service->getRepository()->countByLeadId($leadId);
        $this->assertSame(1, $count);
    }
}
