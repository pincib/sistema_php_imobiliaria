<?php

declare(strict_types=1);

namespace App\Modules\Leads\Repositories;

use App\Database\Connection;
use App\Support\DateHelper;
use PDO;

final class FollowUpRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /**
     * Cria um novo agendamento de follow-up.
     *
     * @param array{
     *     lead_id: int,
     *     user_id: int,
     *     created_by_user_id: int,
     *     scheduled_at: string,
     *     notes?: ?string,
     *     idempotency_key?: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO lead_follow_ups (
                lead_id,
                user_id,
                created_by_user_id,
                scheduled_at,
                status,
                notes,
                idempotency_key,
                created_at,
                updated_at
            ) VALUES (
                :lead_id,
                :user_id,
                :created_by_user_id,
                :scheduled_at,
                \'pending\',
                :notes,
                :idempotency_key,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ');

        $stmt->execute([
            ':lead_id' => $data['lead_id'],
            ':user_id' => $data['user_id'],
            ':created_by_user_id' => $data['created_by_user_id'],
            ':scheduled_at' => $data['scheduled_at'],
            ':notes' => !empty($data['notes']) ? $data['notes'] : null,
            ':idempotency_key' => !empty($data['idempotency_key']) ? $data['idempotency_key'] : null,
        ]);

        $followUpId = (int)$this->pdo->lastInsertId();
        $this->syncLeadNextFollowUp($data['lead_id']);

        return $followUpId;
    }

    /**
     * Localiza follow-up por ID com joins de lead e usuários.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                f.*,
                l.name as lead_name,
                l.phone as lead_phone,
                l.email as lead_email,
                l.stage as lead_stage,
                l.source as lead_source,
                u.name as broker_name,
                u.email as broker_email,
                cu.name as created_by_name,
                cmu.name as completed_by_name
            FROM lead_follow_ups f
            INNER JOIN leads l ON l.id = f.lead_id
            INNER JOIN users u ON u.id = f.user_id
            INNER JOIN users cu ON cu.id = f.created_by_user_id
            LEFT JOIN users cmu ON cmu.id = f.completed_by_user_id
            WHERE f.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Localiza por chave de idempotência.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $key): ?array
    {
        $clean = trim($key);
        if ($clean === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM lead_follow_ups WHERE idempotency_key = :key LIMIT 1');
        $stmt->execute([':key' => $clean]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Localiza o próximo follow-up pendente mais urgente de um lead.
     *
     * @return array<string, mixed>|null
     */
    public function getActivePendingForLead(int $leadId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                f.*,
                u.name as broker_name,
                u.email as broker_email
            FROM lead_follow_ups f
            INNER JOIN users u ON u.id = f.user_id
            WHERE f.lead_id = :lead_id AND f.status = \'pending\'
            ORDER BY f.scheduled_at ASC
            LIMIT 1
        ');
        $stmt->execute([':lead_id' => $leadId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Retorna todos os follow-ups de um lead (histórico completo).
     *
     * @return list<array<string, mixed>>
     */
    public function getAllForLead(int $leadId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                f.*,
                u.name as broker_name,
                cu.name as created_by_name,
                cmu.name as completed_by_name
            FROM lead_follow_ups f
            INNER JOIN users u ON u.id = f.user_id
            INNER JOIN users cu ON cu.id = f.created_by_user_id
            LEFT JOIN users cmu ON cmu.id = f.completed_by_user_id
            WHERE f.lead_id = :lead_id
            ORDER BY f.scheduled_at DESC, f.id DESC
        ');
        $stmt->execute([':lead_id' => $leadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conclui um follow-up com registro do resultado do contato.
     *
     * @param array{
     *     completed_by_user_id: int,
     *     result_notes: string,
     *     completed_at?: ?string,
     *     interaction_id?: ?int
     * } $data
     */
    public function complete(int $id, array $data): bool
    {
        $followUp = $this->findById($id);
        if (!$followUp) {
            return false;
        }

        $completedAt = $data['completed_at'] ?? DateHelper::nowString();

        $stmt = $this->pdo->prepare('
            UPDATE lead_follow_ups SET
                status = \'completed\',
                completed_at = :completed_at,
                completed_by_user_id = :completed_by_user_id,
                result_notes = :result_notes,
                interaction_id = :interaction_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND status = \'pending\'
        ');

        $success = $stmt->execute([
            ':id' => $id,
            ':completed_at' => $completedAt,
            ':completed_by_user_id' => $data['completed_by_user_id'],
            ':result_notes' => $data['result_notes'],
            ':interaction_id' => $data['interaction_id'] ?? null,
        ]);

        if ($success && $stmt->rowCount() === 1) {
            $this->syncLeadNextFollowUp((int)$followUp['lead_id']);
            return true;
        }

        return false;
    }

    /**
     * Reagenda um follow-up pendente.
     *
     * @param array{
     *     scheduled_at: string,
     *     user_id?: ?int,
     *     notes?: ?string
     * } $data
     */
    public function reschedule(int $id, array $data): bool
    {
        $followUp = $this->findById($id);
        if (!$followUp) {
            return false;
        }

        $newUserId = !empty($data['user_id']) ? (int)$data['user_id'] : (int)$followUp['user_id'];
        $newNotes = !empty($data['notes']) ? trim((string)$data['notes']) : $followUp['notes'];

        $stmt = $this->pdo->prepare('
            UPDATE lead_follow_ups SET
                scheduled_at = :scheduled_at,
                user_id = :user_id,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND status = \'pending\'
        ');

        $success = $stmt->execute([
            ':id' => $id,
            ':scheduled_at' => $data['scheduled_at'],
            ':user_id' => $newUserId,
            ':notes' => $newNotes,
        ]);

        if ($success && $stmt->rowCount() === 1) {
            $this->syncLeadNextFollowUp((int)$followUp['lead_id']);
            return true;
        }

        return false;
    }

    /**
     * Sincroniza o campo leads.next_follow_up_at com a menor data pendente em lead_follow_ups.
     */
    public function syncLeadNextFollowUp(int $leadId): void
    {
        $stmtMin = $this->pdo->prepare('
            SELECT MIN(scheduled_at) 
            FROM lead_follow_ups 
            WHERE lead_id = :lead_id AND status = \'pending\'
        ');
        $stmtMin->execute([':lead_id' => $leadId]);
        $nextDate = $stmtMin->fetchColumn();

        $stmtUpdate = $this->pdo->prepare('
            UPDATE leads SET 
                next_follow_up_at = :next_date,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :lead_id
        ');
        $stmtUpdate->execute([
            ':next_date' => $nextDate !== false && $nextDate !== null ? $nextDate : null,
            ':lead_id' => $leadId,
        ]);
    }

    /**
     * Retorna contadores agregados para a dashboard operacional.
     *
     * @return array{overdue: int, today: int, future: int, completed_total: int}
     */
    public function getDashboardCounts(?int $scopedUserId = null): array
    {
        $now = DateHelper::nowString();
        $todayStart = DateHelper::todayStart();
        $todayEnd = DateHelper::todayEnd();

        $userClause = '';
        $params = [];
        if ($scopedUserId !== null) {
            $userClause = ' AND f.user_id = :user_id';
            $params[':user_id'] = $scopedUserId;
        }

        // 1. Atrasados (vencidos): pendentes anteriores a now
        $sqlOverdue = "SELECT COUNT(*) FROM lead_follow_ups f WHERE f.status = 'pending' AND f.scheduled_at < :now {$userClause}";
        $stmt = $this->pdo->prepare($sqlOverdue);
        $stmt->execute(array_merge($params, [':now' => $now]));
        $overdueCount = (int)$stmt->fetchColumn();

        // 2. Para Hoje: pendentes agendados para a data de hoje
        $sqlToday = "SELECT COUNT(*) FROM lead_follow_ups f WHERE f.status = 'pending' AND f.scheduled_at >= :today_start AND f.scheduled_at <= :today_end {$userClause}";
        $stmt = $this->pdo->prepare($sqlToday);
        $stmt->execute(array_merge($params, [
            ':today_start' => $todayStart,
            ':today_end' => $todayEnd,
        ]));
        $todayCount = (int)$stmt->fetchColumn();

        // 3. Futuros: pendentes posteriores ao término de hoje
        $sqlFuture = "SELECT COUNT(*) FROM lead_follow_ups f WHERE f.status = 'pending' AND f.scheduled_at > :today_end {$userClause}";
        $stmt = $this->pdo->prepare($sqlFuture);
        $stmt->execute(array_merge($params, [':today_end' => $todayEnd]));
        $futureCount = (int)$stmt->fetchColumn();

        // 4. Concluídos: total concluídos
        $sqlCompleted = "SELECT COUNT(*) FROM lead_follow_ups f WHERE f.status = 'completed' {$userClause}";
        $stmt = $this->pdo->prepare($sqlCompleted);
        $stmt->execute($params);
        $completedTotal = (int)$stmt->fetchColumn();

        return [
            'overdue' => $overdueCount,
            'today' => $todayCount,
            'future' => $futureCount,
            'completed_total' => $completedTotal,
        ];
    }

    /**
     * Retorna a lista filtrada de follow-ups para a dashboard.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function getFilteredFollowUps(array $filters): array
    {
        $clauses = [];
        $params = [];

        $now = DateHelper::nowString();
        $todayStart = DateHelper::todayStart();
        $todayEnd = DateHelper::todayEnd();

        // 1. Filtro por Situação do Retorno
        $situation = trim((string)($filters['situation'] ?? 'today'));
        if ($situation === 'overdue' || $situation === 'atrasados') {
            $clauses[] = "f.status = 'pending' AND f.scheduled_at < :now";
            $params[':now'] = $now;
        } elseif ($situation === 'today' || $situation === 'hoje') {
            $clauses[] = "f.status = 'pending' AND f.scheduled_at >= :today_start AND f.scheduled_at <= :today_end";
            $params[':today_start'] = $todayStart;
            $params[':today_end'] = $todayEnd;
        } elseif ($situation === 'future' || $situation === 'futuros') {
            $clauses[] = "f.status = 'pending' AND f.scheduled_at > :today_end";
            $params[':today_end'] = $todayEnd;
        } elseif ($situation === 'completed' || $situation === 'concluidos') {
            $clauses[] = "f.status = 'completed'";
        } elseif ($situation === 'all_pending') {
            $clauses[] = "f.status = 'pending'";
        }
        // Se situation for 'all', não filtra por status pré-definido

        // 2. Filtro por Corretor Responsável
        $userId = $filters['user_id'] ?? null;
        if ($userId !== null && $userId !== '' && $userId !== 'all') {
            $clauses[] = 'f.user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }

        // 3. Filtro por Estágio do Lead
        $stage = trim((string)($filters['stage'] ?? ''));
        if ($stage !== '' && $stage !== 'all') {
            $clauses[] = 'l.stage = :lead_stage';
            $params[':lead_stage'] = $stage;
        }

        // 4. Filtro por Período de Datas
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $clauses[] = 'f.scheduled_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $clauses[] = 'f.scheduled_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        // 5. Busca Textual por Lead ou Telefone
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            if ($digits !== '') {
                $clauses[] = '(l.name LIKE :q_text OR l.phone LIKE :q_phone OR f.notes LIKE :q_text)';
                $params[':q_text'] = '%' . $q . '%';
                $params[':q_phone'] = '%' . $digits . '%';
            } else {
                $clauses[] = '(l.name LIKE :q_text OR l.email LIKE :q_text OR f.notes LIKE :q_text)';
                $params[':q_text'] = '%' . $q . '%';
            }
        }

        $whereSql = !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '';

        // Ordenação inteligente: atrasados e hoje ordenam por urgência (ascendente); concluídos ordenam pelo mais recente
        $orderSql = ($situation === 'completed' || $situation === 'concluidos')
            ? 'ORDER BY f.completed_at DESC, f.id DESC'
            : 'ORDER BY f.scheduled_at ASC, f.id ASC';

        $limit = max(1, min(200, (int)($filters['limit'] ?? 100)));

        $sql = "
            SELECT 
                f.*,
                l.name as lead_name,
                l.phone as lead_phone,
                l.email as lead_email,
                l.stage as lead_stage,
                l.source as lead_source,
                u.name as broker_name,
                u.email as broker_email,
                cu.name as created_by_name,
                cmu.name as completed_by_name,
                (
                    SELECT p.code || ' · ' || p.neighborhood
                    FROM lead_properties lp
                    JOIN properties p ON p.id = lp.property_id
                    WHERE lp.lead_id = f.lead_id
                    LIMIT 1
                ) as target_property
            FROM lead_follow_ups f
            INNER JOIN leads l ON l.id = f.lead_id
            INNER JOIN users u ON u.id = f.user_id
            INNER JOIN users cu ON cu.id = f.created_by_user_id
            LEFT JOIN users cmu ON cmu.id = f.completed_by_user_id
            {$whereSql}
            {$orderSql}
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
