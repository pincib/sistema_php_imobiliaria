<?php

declare(strict_types=1);

namespace App\Modules\Properties\Repositories;

use App\Database\Connection;
use PDO;

final class PropertyRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    public function count(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
    }

    /** @return array{data: list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function paginate(int $page, int $perPage, array $filters): array
    {
        $where = [];
        $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(p.code LIKE :q1 OR p.title LIKE :q2 OR p.address LIKE :q3 OR p.neighborhood LIKE :q4 OR p.city LIKE :q5 OR EXISTS (SELECT 1 FROM lead_properties lpx JOIN leads lx ON lx.id=lpx.lead_id WHERE lpx.property_id=p.id AND lx.name LIKE :q6))';
            for ($i = 1; $i <= 6; $i++) $params[':q' . $i] = '%' . $filters['q'] . '%';
        }
        foreach (['status', 'type', 'city'] as $key) {
            if ($filters[$key] !== '') {
                $where[] = 'p.' . $key . ' = :' . $key;
                $params[':' . $key] = $filters[$key];
            }
        }
        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM properties p {$clause}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $stmt = $this->pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM lead_properties lp WHERE lp.property_id=p.id) AS lead_count FROM properties p {$clause} ORDER BY p.updated_at DESC, p.id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $pages];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id=:id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByCode(string $code, ?int $exceptId = null): ?array
    {
        $sql = 'SELECT * FROM properties WHERE code=:code COLLATE NOCASE';
        if ($exceptId !== null) $sql .= ' AND id <> :id';
        $stmt = $this->pdo->prepare($sql);
        $params = [':code' => $code];
        if ($exceptId !== null) $params[':id'] = $exceptId;
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO properties(code,title,type,price,status,address,neighborhood,city,characteristics,notes,google_maps_url,built_area,land_area,intention,sale_price,rent_price,aspiration,condominium_price,iptu_price,listing_url,created_at,updated_at) VALUES(:code,:title,:type,:price,:status,:address,:neighborhood,:city,:characteristics,:notes,:google_maps_url,:built_area,:land_area,:intention,:sale_price,:rent_price,:aspiration,:condominium_price,:iptu_price,:listing_url,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data[':id'] = $id;
        $stmt = $this->pdo->prepare('UPDATE properties SET code=:code,title=:title,type=:type,price=:price,status=:status,address=:address,neighborhood=:neighborhood,city=:city,characteristics=:characteristics,notes=:notes,google_maps_url=:google_maps_url,built_area=:built_area,land_area=:land_area,intention=:intention,sale_price=:sale_price,rent_price=:rent_price,aspiration=:aspiration,condominium_price=:condominium_price,iptu_price=:iptu_price,listing_url=:listing_url,import_batch_id=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute($data);
    }

    public function relatedLeads(int $propertyId): array
    {
        $stmt = $this->pdo->prepare('SELECT l.id,l.name,l.phone,l.email,l.stage,lp.interest_type,lp.notes,lp.created_at AS associated_at FROM lead_properties lp JOIN leads l ON l.id=lp.lead_id WHERE lp.property_id=:id ORDER BY lp.created_at DESC');
        $stmt->execute([':id' => $propertyId]);
        return $stmt->fetchAll();
    }

    public function association(int $leadId, int $propertyId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT lp.*,l.name AS lead_name,p.code,p.title FROM lead_properties lp JOIN leads l ON l.id=lp.lead_id JOIN properties p ON p.id=lp.property_id WHERE lp.lead_id=:lead AND lp.property_id=:property');
        $stmt->execute([':lead' => $leadId, ':property' => $propertyId]);
        return $stmt->fetch() ?: null;
    }

    public function link(int $leadId, int $propertyId, string $interest, ?string $notes): bool
    {
        $stmt = $this->pdo->prepare('INSERT INTO lead_properties(lead_id,property_id,interest_type,notes,created_at) VALUES(:lead,:property,:interest,:notes,CURRENT_TIMESTAMP) ON CONFLICT(lead_id,property_id) DO NOTHING');
        $stmt->execute([':lead' => $leadId, ':property' => $propertyId, ':interest' => $interest, ':notes' => $notes]);
        return $stmt->rowCount() > 0;
    }

    public function unlink(int $leadId, int $propertyId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM lead_properties WHERE lead_id=:lead AND property_id=:property');
        $stmt->execute([':lead' => $leadId, ':property' => $propertyId]);
        return $stmt->rowCount() > 0;
    }

    public function searchLeads(string $query): array
    {
        $stmt = $this->pdo->prepare('SELECT id,name,phone,email FROM leads WHERE name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3 ORDER BY name LIMIT 30');
        $like = '%' . $query . '%';
        $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
        return $stmt->fetchAll();
    }

    public function cities(): array
    {
        return $this->pdo->query("SELECT DISTINCT city FROM properties WHERE city IS NOT NULL AND city<>'' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
    }
}
