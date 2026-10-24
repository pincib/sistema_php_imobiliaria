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
        return (int)$this->pdo->query('SELECT COUNT(*) FROM signboards WHERE deleted_at IS NULL')->fetchColumn();
    }

    public function countByStatus(): array
    {
        $sql = "SELECT CASE WHEN sb.removed_at IS NOT NULL THEN 'retirada' WHEN sb.installed_at IS NULL THEN 'pendente' WHEN sb.expires_at IS NOT NULL AND date(sb.expires_at) < :today THEN 'vencida' ELSE 'ativa' END status, COUNT(*) total FROM signboards sb WHERE sb.deleted_at IS NULL GROUP BY status";
        $counts = ['ativa' => 0, 'vencida' => 0, 'pendente' => 0, 'retirada' => 0];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today'=>date('Y-m-d')]);
        foreach ($stmt->fetchAll() as $row) $counts[$row['status']] = (int)$row['total'];
        return $counts;
    }

    public function paginate(int $page, int $perPage, array $filters): array
    {
        $where = ['sb.deleted_at IS NULL'];
        $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(p.code LIKE :q1 OR p.title LIKE :q2 OR u.name LIKE :q3 OR p.address LIKE :q4 OR p.neighborhood LIKE :q5 OR p.city LIKE :q6)';
            foreach (range(1, 6) as $i) $params[':q' . $i] = '%' . $filters['q'] . '%';
        }
        $today = date('Y-m-d');
        $statusSql = "CASE WHEN sb.removed_at IS NOT NULL THEN 'retirada' WHEN sb.installed_at IS NULL THEN 'pendente' WHEN sb.expires_at IS NOT NULL AND date(sb.expires_at) < :today THEN 'vencida' ELSE 'ativa' END";
        if ($filters['status'] !== '' && in_array($filters['status'], ['ativa','vencida','pendente','retirada'], true)) {
            $where[] = "({$statusSql}) = :status";
            $params[':status'] = $filters['status'];
            $params[':today'] = $today;
        }
        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sqlFrom = "FROM signboards sb LEFT JOIN properties p ON p.id=sb.property_id JOIN users u ON u.id=sb.responsible_user_id {$clause}";
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

    public function board(array $filters): array
    {
        $where = ['sb.deleted_at IS NULL'];
        $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(p.code LIKE :q1 OR p.title LIKE :q2 OR u.name LIKE :q3 OR p.address LIKE :q4 OR p.neighborhood LIKE :q5 OR p.city LIKE :q6)';
            foreach (range(1, 6) as $i) $params[':q'.$i] = '%'.$filters['q'].'%';
        }
        $today = date('Y-m-d');
        $statusSql = "CASE WHEN sb.removed_at IS NOT NULL THEN 'retirada' WHEN sb.installed_at IS NULL THEN 'pendente' WHEN sb.expires_at IS NOT NULL AND date(sb.expires_at) < :today THEN 'vencida' ELSE 'ativa' END";
        if ($filters['status'] !== '' && in_array($filters['status'], ['ativa','vencida','pendente','retirada'], true)) {
            $where[] = "({$statusSql}) = :status";
            $params[':status'] = $filters['status'];
            $params[':today'] = $today;
        }
        $clause = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $sql = "SELECT sb.*, p.code AS property_code, p.title AS property_title, p.address, p.neighborhood, p.city, u.name AS responsible_name, {$statusSql} AS operational_status FROM signboards sb LEFT JOIN properties p ON p.id=sb.property_id JOIN users u ON u.id=sb.responsible_user_id {$clause} ORDER BY sb.id DESC";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key=>$value) $stmt->bindValue($key,$value);
        if (!array_key_exists(':today', $params)) $stmt->bindValue(':today',$today);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT sb.*, p.code AS property_code, p.title AS property_title, p.address, p.neighborhood, p.city, u.name AS responsible_name, CASE WHEN sb.removed_at IS NOT NULL THEN 'retirada' WHEN sb.installed_at IS NULL THEN 'pendente' WHEN sb.expires_at IS NOT NULL AND date(sb.expires_at) < :today THEN 'vencida' ELSE 'ativa' END AS operational_status FROM signboards sb LEFT JOIN properties p ON p.id=sb.property_id JOIN users u ON u.id=sb.responsible_user_id WHERE sb.id=:id AND sb.deleted_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':today'=>date('Y-m-d')]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO signboards(property_id,location,responsible_user_id,expires_at,notes,material,height_cm,length_cm,deal_type,price,created_by_user_id,created_at,updated_at) VALUES(:property_id,:location,:responsible_user_id,:expires_at,:notes,:material,:height_cm,:length_cm,:deal_type,:price,:created_by_user_id,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt=$this->pdo->prepare('UPDATE signboards SET responsible_user_id=:responsible_user_id,material=:material,height_cm=:height_cm,length_cm=:length_cm,deal_type=:deal_type,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND removed_at IS NULL AND deleted_at IS NULL');
        $stmt->execute([':responsible_user_id'=>$data[':responsible_user_id'], ':material'=>$data[':material'], ':height_cm'=>$data[':height_cm'], ':length_cm'=>$data[':length_cm'], ':deal_type'=>$data[':deal_type'], ':id'=>$id]);
        return $stmt->rowCount() > 0;
    }

    public function markInstalled(int $id, int $actorId, string $date, string $note, string $fromStage): bool
    {
        $stmt=$this->pdo->prepare("UPDATE signboards SET installed_at=:date, install_notes=:note, board_stage='usadas', updated_at=CURRENT_TIMESTAMP WHERE id=:id AND installed_at IS NULL AND removed_at IS NULL AND deleted_at IS NULL");
        $stmt->execute([':date'=>$date, ':note'=>$note, ':id'=>$id]);
        if ($stmt->rowCount() === 0) return false;
        $this->addMovement($id, 'installed', $date, $note, $actorId);
        if ($fromStage !== 'usadas') {
            $this->pdo->prepare('INSERT INTO signboard_stage_history(signboard_id,from_stage,to_stage,user_id) VALUES(:id,:from,\'usadas\',:user)')->execute([':id'=>$id, ':from'=>$fromStage, ':user'=>$actorId]);
        }
        return true;
    }

    public function markRemoved(int $id, int $actorId, string $date, string $note): bool
    {
        $stmt=$this->pdo->prepare('UPDATE signboards SET removed_at=:date, removal_notes=:note, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND installed_at IS NOT NULL AND removed_at IS NULL AND deleted_at IS NULL');
        $stmt->execute([':date'=>$date, ':note'=>$note, ':id'=>$id]);
        if ($stmt->rowCount() === 0) return false;
        $this->addMovement($id, 'removed', $date, $note, $actorId);
        return true;
    }

    public function changeStage(int $id, string $from, string $to, int $actorId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE signboards SET board_stage=:to, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND board_stage=:from AND deleted_at IS NULL');
        $stmt->execute([':to'=>$to, ':id'=>$id, ':from'=>$from]);
        if ($stmt->rowCount() === 0) return false;
        $this->pdo->prepare('INSERT INTO signboard_stage_history(signboard_id,from_stage,to_stage,user_id) VALUES(:id,:from,:to,:user)')->execute([':id'=>$id, ':from'=>$from, ':to'=>$to, ':user'=>$actorId]);
        return true;
    }

    public function archive(int $id, int $actorId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE signboards SET deleted_at=CURRENT_TIMESTAMP,deleted_by_user_id=:actor,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':actor'=>$actorId, ':id'=>$id]);
        return $stmt->rowCount() > 0;
    }

    public function assignToProperty(int $signboardId, int $propertyId, int $actorId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE signboards SET property_id=:property,board_stage='usadas',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND property_id IS NULL AND board_stage='disponivel' AND deleted_at IS NULL");
        $stmt->execute([':property'=>$propertyId, ':id'=>$signboardId]);
        if ($stmt->rowCount() === 0) return false;
        $this->pdo->prepare("INSERT INTO signboard_stage_history(signboard_id,from_stage,to_stage,user_id) VALUES(:id,'disponivel','usadas',:user)")->execute([':id'=>$signboardId, ':user'=>$actorId]);
        $this->pdo->prepare('INSERT INTO signboard_property_history(signboard_id,property_id,user_id) VALUES(:id,:property,:user)')->execute([':id'=>$signboardId, ':property'=>$propertyId, ':user'=>$actorId]);
        return true;
    }

    public function addMovement(int $id, string $type, string $date, string $note, int $actorId): void
    {
        $this->pdo->prepare('INSERT INTO signboard_movements(signboard_id,movement_type,moved_at,notes,user_id,created_at) VALUES(:id,:type,:date,:notes,:user,CURRENT_TIMESTAMP)')->execute([':id'=>$id, ':type'=>$type, ':date'=>$date, ':notes'=>$note, ':user'=>$actorId]);
    }

    public function movements(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT movement_type, moved_at, notes, user_name FROM (SELECT m.id, m.movement_type, m.moved_at, m.notes, u.name AS user_name FROM signboard_movements m LEFT JOIN users u ON u.id=m.user_id WHERE m.signboard_id=:id UNION ALL SELECT h.id+1000000000 AS id, 'stage_changed' AS movement_type, h.changed_at AS moved_at, 'Etapa alterada: ' || CASE h.from_stage WHEN 'a_fazer' THEN 'A fazer' WHEN 'pago' THEN 'Pago' WHEN 'pronto' THEN 'Pronto' WHEN 'disponivel' THEN 'Disponível' ELSE 'Usadas' END || ' → ' || CASE h.to_stage WHEN 'a_fazer' THEN 'A fazer' WHEN 'pago' THEN 'Pago' WHEN 'pronto' THEN 'Pronto' WHEN 'disponivel' THEN 'Disponível' ELSE 'Usadas' END AS notes, u.name AS user_name FROM signboard_stage_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.signboard_id=:stage_id UNION ALL SELECT ph.id+2000000000 AS id, 'assigned' AS movement_type, ph.assigned_at AS moved_at, 'Placa colocada no imóvel: ' || p.code || ' · ' || p.title AS notes, u.name AS user_name FROM signboard_property_history ph JOIN properties p ON p.id=ph.property_id LEFT JOIN users u ON u.id=ph.user_id WHERE ph.signboard_id=:assignment_id) ORDER BY moved_at DESC, id DESC");
        $stmt->execute([':id' => $id, ':stage_id'=>$id, ':assignment_id'=>$id]);
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

    public function validResponsible(int $userId): bool
    {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE id=:user AND is_active=1');
        $stmt->execute([':user'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function propertyExists(int $propertyId): bool
    {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM properties WHERE id=:id');
        $stmt->execute([':id'=>$propertyId]);
        return (bool)$stmt->fetchColumn();
    }
}
