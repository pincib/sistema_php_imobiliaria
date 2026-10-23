<?php

declare(strict_types=1);

namespace App\Modules\Leads\Repositories;

use App\Database\Connection;
use PDO;

final class InteractionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /**
     * Insere uma nova interação no histórico do lead.
     *
     * @param array{
     *     lead_id: int,
     *     user_id: int,
     *     type: string,
     *     notes: string,
     *     contacted_at: string,
     *     idempotency_key?: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO lead_interactions (
                lead_id,
                user_id,
                type,
                notes,
                contacted_at,
                idempotency_key,
                created_at
            ) VALUES (
                :lead_id,
                :user_id,
                :type,
                :notes,
                :contacted_at,
                :idempotency_key,
                CURRENT_TIMESTAMP
            )
        ');

        $stmt->execute([
            ':lead_id' => $data['lead_id'],
            ':user_id' => $data['user_id'],
            ':type' => $data['type'],
            ':notes' => $data['notes'],
            ':contacted_at' => $data['contacted_at'],
            ':idempotency_key' => !empty($data['idempotency_key']) ? $data['idempotency_key'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Localiza uma interação pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                i.*,
                u.name as author_name,
                u.email as author_email,
                r.label as author_role_label
            FROM lead_interactions i
            INNER JOIN users u ON u.id = i.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE i.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Localiza interação pela chave de idempotência (proteção contra duplo clique e reenvios).
     *
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $key): ?array
    {
        $clean = trim($key);
        if ($clean === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM lead_interactions WHERE idempotency_key = :key LIMIT 1');
        $stmt->execute([':key' => $clean]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Retorna a lista cronológica de interações de um lead (mais recentes primeiro).
     *
     * @return list<array<string, mixed>>
     */
    public function getByLeadId(int $leadId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                i.*,
                u.name as author_name,
                u.email as author_email,
                r.label as author_role_label
            FROM lead_interactions i
            INNER JOIN users u ON u.id = i.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE i.lead_id = :lead_id
            ORDER BY i.contacted_at DESC, i.id DESC
        ');
        $stmt->execute([':lead_id' => $leadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a quantidade total de interações registradas para um lead.
     */
    public function countByLeadId(int $leadId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM lead_interactions WHERE lead_id = :lead_id');
        $stmt->execute([':lead_id' => $leadId]);

        return (int)$stmt->fetchColumn();
    }
}
