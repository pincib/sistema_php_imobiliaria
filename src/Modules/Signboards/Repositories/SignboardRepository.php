<?php

declare(strict_types=1);

namespace App\Modules\Signboards\Repositories;

use App\Database\Connection;
use PDO;

final class SignboardRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    public function count(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM signboards')->fetchColumn();
    }

    public function countByStatus(): array
    {
        $sql = "SELECT CASE WHEN sb.removed_at IS NOT NULL THEN 'retirada' WHEN sb.installed_at IS NULL THEN 'pendente' WHEN sb.expires_at IS NOT NULL AND date(sb.expires_at) < :today THEN 'vencida' ELSE 'ativa' END status, COUNT(*) total FROM signboards sb GROUP BY status";
        $counts = ['ativa' => 0, 'vencida' => 0, 'pendente' => 0, 'retirada' => 0];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today'=>date('Y-m-d')]);
        foreach ($stmt->fetchAll() as $row) $counts[$row['status']] = (int)$row['total'];
        return $counts;
    }

    public function paginate(int $page, int $perPage, array $filters): array
    {
        $where = [];
        $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(sb.location LIKE :q1 OR sb.notes LIKE :q2 OR p.code LIKE :q3 OR p.title LIKE :q4 OR u.name LIKE :q5 OR p.address LIKE :q6 OR p.neighborhood LIKE :q7 OR p.city LIKE :q8)';
            foreach (range(1, 8) as $i) $params[':q' . $i] = '%' . $filters['q'] . '%';
        }
        $today = date('Y-m-d');
        $statusSql = "CASE WHEN sb.removed_at IS NOT NULL THEN 'retirada' WHEN sb.installed_at IS NULL THEN 'pendente' WHEN sb.expires_at IS NOT NULL AND date(sb.expires_at) < :today THEN 'vencida' ELSE 'ativa' END";
        if ($filters['status'] !== '' && in_array($filters['status'], ['ativa','vencida','pendente','retirada'], true)) {
            $where[] = "({$statusSql}) = :status";
            $params[':status'] = $filters['status'];
            $params[':today'] = $today;
        }
        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sqlFrom = "FROM signboards sb JOIN properties p ON p.id=sb.property_id JOIN users u ON u.id=sb.responsible_user_id {$clause}";
        $stmt = $this->pdo->prepare("SELECT COUNT(*) {$sqlFrom}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $stmt = $this->pdo->prepare("SELECT sb.*, p.code AS property_code, p.title AS property_title, p.address, p.neighborhood, p.city, u.name AS responsible_name, {$statusSql} AS operational_status {$sqlFrom} ORDER BY COALESCE(sb.installed_at, sb.created_at) DESC, sb.id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        if (!array_key_exists(':today',$params)) $stmt->bindValue(':today',$today);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $pages];
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT sb.*, p.code AS property_code, p.title AS property_title, p.address, p.neighborhood, p.city, u.name AS responsible_name, CASE WHEN sb.removed_at IS NOT NULL THEN 'retirada' WHEN sb.installed_at IS NULL THEN 'pendente' WHEN sb.expires_at IS NOT NULL AND date(sb.expires_at) < :today THEN 'vencida' ELSE 'ativa' END AS operational_status FROM signboards sb JOIN properties p ON p.id=sb.property_id JOIN users u ON u.id=sb.responsible_user_id WHERE sb.id=:id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':today'=>date('Y-m-d')]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO signboards(property_id,location,responsible_user_id,expires_at,notes,created_by_user_id,created_at,updated_at) VALUES(:property_id,:location,:responsible_user_id,:expires_at,:notes,:created_by_user_id,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data[':id'] = $id;
        $stmt=$this->pdo->prepare('UPDATE signboards SET property_id=:property_id,location=:location,responsible_user_id=:responsible_user_id,expires_at=:expires_at,notes=:notes,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND removed_at IS NULL');
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function markInstalled(int $id, int $actorId, string $date, string $note): bool
    {
        $stmt=$this->pdo->prepare('UPDATE signboards SET installed_at=:date, install_notes=:note, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND installed_at IS NULL AND removed_at IS NULL');
        $stmt->execute([':date'=>$date, ':note'=>$note, ':id'=>$id]);
        if ($stmt->rowCount() === 0) return false;
        $this->addMovement($id, 'installed', $date, $note, $actorId);
        return true;
    }

    public function markRemoved(int $id, int $actorId, string $date, string $note): bool
    {
        $stmt=$this->pdo->prepare('UPDATE signboards SET removed_at=:date, removal_notes=:note, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND installed_at IS NOT NULL AND removed_at IS NULL');
        $stmt->execute([':date'=>$date, ':note'=>$note, ':id'=>$id]);
        if ($stmt->rowCount() === 0) return false;
        $this->addMovement($id, 'removed', $date, $note, $actorId);
        return true;
    }

    public function addMovement(int $id, string $type, string $date, string $note, int $actorId): void
    {
        $this->pdo->prepare('INSERT INTO signboard_movements(signboard_id,movement_type,moved_at,notes,user_id,created_at) VALUES(:id,:type,:date,:notes,:user,CURRENT_TIMESTAMP)')->execute([':id'=>$id, ':type'=>$type, ':date'=>$date, ':notes'=>$note, ':user'=>$actorId]);
    }

    public function movements(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT m.*,u.name AS user_name FROM signboard_movements m LEFT JOIN users u ON u.id=m.user_id WHERE m.signboard_id=:id ORDER BY m.id DESC');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    public function properties(): array
    {
        return $this->pdo->query('SELECT id,code,title,address,neighborhood,city FROM properties ORDER BY code')->fetchAll();
    }

    public function activeUsers(): array
    {
        return $this->pdo->query('SELECT id,name FROM users WHERE is_active=1 ORDER BY name')->fetchAll();
    }

    public function validReferences(int $propertyId, int $userId): bool
    {
        $stmt=$this->pdo->prepare('SELECT (SELECT COUNT(*) FROM properties WHERE id=:property) > 0 AND (SELECT COUNT(*) FROM users WHERE id=:user AND is_active=1) > 0');
        $stmt->execute([':property'=>$propertyId,':user'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }
}
