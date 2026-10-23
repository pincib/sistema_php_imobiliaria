<?php

declare(strict_types=1);

namespace App\Modules\Leads\Repositories;

use App\Database\Connection;
use PDO;

final class LeadRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /**
     * Cria um novo lead e retorna seu ID gerado.
     *
     * @param array{
     *     name: string,
     *     email?: ?string,
     *     phone?: ?string,
     *     source?: string,
     *     stage?: string,
     *     loss_reason?: ?string,
     *     user_id?: ?int,
     *     notes?: ?string,
     *     next_follow_up_at?: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO leads (
                name,
                email,
                phone,
                source,
                stage,
                loss_reason,
                user_id,
                notes,
                next_follow_up_at,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :email,
                :phone,
                :source,
                :stage,
                :loss_reason,
                :user_id,
                :notes,
                :next_follow_up_at,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ');

        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':source' => $data['source'] ?? 'manual',
            ':stage' => $data['stage'] ?? 'novo',
            ':loss_reason' => $data['loss_reason'] ?? null,
            ':user_id' => !empty($data['user_id']) ? (int)$data['user_id'] : null,
            ':notes' => $data['notes'] ?? null,
            ':next_follow_up_at' => $data['next_follow_up_at'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atualiza dados cadastrais de um lead existente.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE leads SET
                name = :name,
                email = :email,
                phone = :phone,
                source = :source,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':source' => $data['source'] ?? 'manual',
            ':notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Atualiza o estágio e o motivo de perda de um lead.
     */
    public function updateStage(int $id, string $stage, ?string $lossReason = null): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE leads SET
                stage = :stage,
                loss_reason = :loss_reason,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        return $stmt->execute([
            ':id' => $id,
            ':stage' => $stage,
            ':loss_reason' => $stage === 'perdido' ? $lossReason : null,
        ]);
    }

    /**
     * Realiza a troca de responsável pelo lead e registra no histórico de atribuição.
     */
    public function reassign(int $leadId, int $newUserId, int $assignedByUserId, string $reason): bool
    {
        return Connection::transaction(function (PDO $pdo) use ($leadId, $newUserId, $assignedByUserId, $reason): bool {
            // 1. Obtém o responsável atual
            $stmtLead = $pdo->prepare('SELECT user_id FROM leads WHERE id = :id');
            $stmtLead->execute([':id' => $leadId]);
            $currentUserId = $stmtLead->fetchColumn();
            $fromUserId = $currentUserId !== false && $currentUserId !== null ? (int)$currentUserId : null;

            // 2. Atualiza o lead
            $stmtUpdate = $pdo->prepare('
                UPDATE leads SET
                    user_id = :new_user_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $stmtUpdate->execute([
                ':id' => $leadId,
                ':new_user_id' => $newUserId,
            ]);

            // 3. Registra na tabela de histórico de atribuições
            $stmtAssign = $pdo->prepare('
                INSERT INTO lead_assignments (
                    lead_id,
                    from_user_id,
                    to_user_id,
                    assigned_by_user_id,
                    reason,
                    assigned_at
                ) VALUES (
                    :lead_id,
                    :from_user_id,
                    :to_user_id,
                    :assigned_by_user_id,
                    :reason,
                    CURRENT_TIMESTAMP
                )
            ');
            $stmtAssign->execute([
                ':lead_id' => $leadId,
                ':from_user_id' => $fromUserId,
                ':to_user_id' => $newUserId,
                ':assigned_by_user_id' => $assignedByUserId,
                ':reason' => $reason,
            ]);

            return true;
        });
    }

    /**
     * Localiza um lead pelo ID com dados do corretor responsável.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                l.*,
                u.name as assigned_user_name,
                u.email as assigned_user_email,
                r.label as assigned_user_role_label
            FROM leads l
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE l.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lead ?: null;
    }

    /**
     * Busca lead por e-mail (para detecção de duplicidade).
     */
    public function findByEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM leads WHERE email = :email';
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lead ?: null;
    }

    /**
     * Busca lead por telefone (para detecção de duplicidade).
     */
    public function findByPhone(string $phone, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM leads WHERE phone = :phone';
        $params = [':phone' => $phone];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lead ?: null;
    }

    /**
     * Remove um lead do banco de dados (exclusivo para administradores).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM leads WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Retorna a quantidade total de leads de acordo com filtros opcionais.
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        $exists = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='leads'")->fetchColumn();
        if (!$exists) {
            return 0;
        }

        [$whereSql, $params] = $this->buildFilterConditions($filters);
        $sql = "SELECT COUNT(*) FROM leads l {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Retorna a contagem de leads agrupados por estágio.
     *
     * @return array<string, int>
     */
    public function countByStage(): array
    {
        $exists = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='leads'")->fetchColumn();
        if (!$exists) {
            return [];
        }

        $stmt = $this->pdo->query('
            SELECT stage, COUNT(*) as total 
            FROM leads 
            GROUP BY stage
        ');

        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[(string)$row['stage']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * Retorna listagem paginada de leads com busca e filtros no servidor.
     *
     * @param array<string, mixed> $filters
     * @return array{
     *     data: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     total_pages: int
     * }
     */
    public function paginate(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $exists = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='leads'")->fetchColumn();
        if (!$exists) {
            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildFilterConditions($filters);

        // 1. Total de registros filtrados
        $countSql = "SELECT COUNT(*) FROM leads l {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        if ($page > $totalPages && $total > 0) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        // 2. Consulta paginada dos registros com joins necessários
        $sql = "
            SELECT 
                l.*,
                u.name as assigned_user_name,
                u.email as assigned_user_email
            FROM leads l
            LEFT JOIN users u ON u.id = l.user_id
            {$whereSql}
            ORDER BY l.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Retorna o histórico de transferências de responsável de um lead.
     *
     * @return list<array<string, mixed>>
     */
    public function getAssignmentsHistory(int $leadId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                a.*,
                fu.name as from_user_name,
                tu.name as to_user_name,
                bu.name as assigned_by_name
            FROM lead_assignments a
            LEFT JOIN users fu ON fu.id = a.from_user_id
            LEFT JOIN users tu ON tu.id = a.to_user_id
            LEFT JOIN users bu ON bu.id = a.assigned_by_user_id
            WHERE a.lead_id = :lead_id
            ORDER BY a.id DESC
        ');
        $stmt->execute([':lead_id' => $leadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a lista de imóveis associados ao lead (Relação N:N).
     *
     * @return list<array<string, mixed>>
     */
    public function getAssociatedProperties(int $leadId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                p.id as property_id,
                p.code,
                p.title,
                p.type,
                p.price,
                p.neighborhood,
                p.city,
                p.status as property_status,
                lp.interest_type,
                lp.notes as association_notes,
                lp.created_at as associated_at
            FROM lead_properties lp
            INNER JOIN properties p ON p.id = lp.property_id
            WHERE lp.lead_id = :lead_id
            ORDER BY lp.id DESC
        ');
        $stmt->execute([':lead_id' => $leadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Associa um imóvel a um lead (Relação N:N).
     */
    public function associateProperty(int $leadId, int $propertyId, string $interestType = 'interesse', ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO lead_properties (lead_id, property_id, interest_type, notes, created_at)
            VALUES (:lead_id, :property_id, :interest_type, :notes, CURRENT_TIMESTAMP)
            ON CONFLICT(lead_id, property_id) DO NOTHING
        ');
        $stmt->execute([
            ':lead_id' => $leadId,
            ':property_id' => $propertyId,
            ':interest_type' => $interestType,
            ':notes' => $notes,
        ]);
    }

    public function getAssociatedProperty(int $leadId, int $propertyId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT lp.*, p.code, p.title, l.name AS lead_name FROM lead_properties lp JOIN properties p ON p.id = lp.property_id JOIN leads l ON l.id = lp.lead_id WHERE lp.lead_id = :lead_id AND lp.property_id = :property_id');
        $stmt->execute([':lead_id' => $leadId, ':property_id' => $propertyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Remove a associação entre lead e imóvel.
     */
    public function disassociateProperty(int $leadId, int $propertyId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM lead_properties WHERE lead_id = :lead_id AND property_id = :property_id');
        $stmt->execute([
            ':lead_id' => $leadId,
            ':property_id' => $propertyId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna imóveis ainda não associados a este lead.
     *
     * @return list<array<string, mixed>>
     */
    public function getAvailableProperties(int $leadId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.* 
            FROM properties p
            WHERE p.id NOT IN (
                SELECT property_id FROM lead_properties WHERE lead_id = :lead_id
            )
            ORDER BY p.code ASC
        ');
        $stmt->execute([':lead_id' => $leadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna lista de usuários corretores e administradores ativos para seleção de responsável.
     *
     * @return list<array{id: int, name: string, email: string, role_name: string}>
     */
    public function getActiveBrokers(): array
    {
        $stmt = $this->pdo->query('
            SELECT u.id, u.name, u.email, r.name as role_name, r.label as role_label
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.is_active = 1
            ORDER BY u.name ASC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isActiveAssignee(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Monta as cláusulas WHERE e parâmetros para busca e filtros de leads.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilterConditions(array $filters): array
    {
        $clauses = [];
        $params = [];

        // 1. Busca por texto livre (nome, e-mail, telefone)
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            if ($digits !== '') {
                $clauses[] = '(l.name LIKE :q_text OR l.email LIKE :q_text OR l.phone LIKE :q_phone)';
                $params[':q_text'] = '%' . $q . '%';
                $params[':q_phone'] = '%' . $digits . '%';
            } else {
                $clauses[] = '(l.name LIKE :q_text OR l.email LIKE :q_text)';
                $params[':q_text'] = '%' . $q . '%';
            }
        }

        // 2. Filtro por estágio
        $stage = trim((string)($filters['stage'] ?? ''));
        if ($stage !== '' && $stage !== 'all') {
            $clauses[] = 'l.stage = :stage';
            $params[':stage'] = $stage;
        }

        // 3. Filtro por responsável
        $userId = $filters['user_id'] ?? null;
        if ($userId !== null && $userId !== '' && $userId !== 'all') {
            if ($userId === 'unassigned') {
                $clauses[] = 'l.user_id IS NULL';
            } else {
                $clauses[] = 'l.user_id = :user_id';
                $params[':user_id'] = (int)$userId;
            }
        }

        // 4. Filtro por origem
        $source = trim((string)($filters['source'] ?? ''));
        if ($source !== '' && $source !== 'all') {
            $clauses[] = 'l.source = :source';
            $params[':source'] = $source;
        }

        $whereSql = !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }
}
