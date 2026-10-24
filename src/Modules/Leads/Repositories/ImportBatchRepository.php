<?php

declare(strict_types=1);

namespace App\Modules\Leads\Repositories;

use App\Database\Connection;
use PDO;

final class ImportBatchRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /**
     * Cria um novo registro de lote de importação.
     *
     * @param array{
     *     batch_code: string,
     *     user_id: int,
     *     filename: string,
     *     file_hash: string,
     *     file_size: int,
     *     total_rows?: int,
     *     created_count?: int,
     *     updated_count?: int,
     *     skipped_count?: int,
     *     error_count?: int,
     *     duplicate_policy?: string,
     *     status?: string,
     *     summary_json?: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO import_batches (
                batch_code,
                user_id,
                filename,
                file_hash,
                file_size,
                total_rows,
                created_count,
                updated_count,
                skipped_count,
                error_count,
                duplicate_policy,
                status,
                summary_json,
                data_kind,
                created_at
            ) VALUES (
                :batch_code,
                :user_id,
                :filename,
                :file_hash,
                :file_size,
                :total_rows,
                :created_count,
                :updated_count,
                :skipped_count,
                :error_count,
                :duplicate_policy,
                :status,
                :summary_json,
                :data_kind,
                CURRENT_TIMESTAMP
            )
        ');

        $stmt->execute([
            ':batch_code' => $data['batch_code'],
            ':user_id' => $data['user_id'],
            ':filename' => $data['filename'],
            ':file_hash' => $data['file_hash'],
            ':file_size' => $data['file_size'],
            ':total_rows' => $data['total_rows'] ?? 0,
            ':created_count' => $data['created_count'] ?? 0,
            ':updated_count' => $data['updated_count'] ?? 0,
            ':skipped_count' => $data['skipped_count'] ?? 0,
            ':error_count' => $data['error_count'] ?? 0,
            ':duplicate_policy' => $data['duplicate_policy'] ?? 'merge',
            ':status' => $data['status'] ?? 'completed',
            ':summary_json' => $data['summary_json'] ?? null,
            ':data_kind' => $data['data_kind'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atualiza o status, contagens e sumário de um lote.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = [
            'total_rows', 'created_count', 'updated_count',
            'skipped_count', 'error_count', 'duplicate_policy',
            'status', 'summary_json', 'cleanup_supported', 'cleaned_at'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE import_batches SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Localiza um lote pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT b.*, u.name as user_name, u.email as user_email
            FROM import_batches b
            LEFT JOIN users u ON u.id = b.user_id
            WHERE b.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Localiza um lote pelo código único.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT b.*, u.name as user_name, u.email as user_email
            FROM import_batches b
            LEFT JOIN users u ON u.id = b.user_id
            WHERE b.batch_code = :code
            LIMIT 1
        ');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Localiza o lote mais recente concluído com o mesmo hash de arquivo.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestByHash(string $hash): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.name as user_name, u.email as user_email
            FROM import_batches b
            LEFT JOIN users u ON u.id = b.user_id
            WHERE b.file_hash = :hash AND b.status = 'completed'
            ORDER BY b.id DESC
            LIMIT 1
        ");
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Retorna a listagem dos lotes de importação mais recentes.
     *
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('
            SELECT b.*, u.name as user_name, u.email as user_email
            FROM import_batches b
            LEFT JOIN users u ON u.id = b.user_id
            ORDER BY b.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
