<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Repositories;

use App\Database\Connection;
use App\Modules\Properties\Services\PropertyService;
use App\Support\DateHelper;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Read-only queries over current lead_properties associations. */
final class PropertiesDashboardRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    public function topProperties(array $filters, int $limit = 3): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $statement = $this->pdo->prepare("SELECT p.id, p.code, p.title, p.type, p.status, COUNT(DISTINCT l.id) AS lead_count
            FROM properties p JOIN lead_properties lp ON lp.property_id = p.id JOIN leads l ON l.id = lp.lead_id
            {$where} GROUP BY p.id ORDER BY lead_count DESC, p.code COLLATE NOCASE ASC LIMIT :limit");
        $this->execute($statement, $params, [':limit' => [$limit, PDO::PARAM_INT]]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Minimal property identity for the selected contribution panel. */
    public function findProperty(int $propertyId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, code, title, type, status FROM properties WHERE id = :id');
        $statement->execute([':id' => $propertyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $filters @return list<array{type:string,label:string,lead_count:int}> */
    public function leadCountsByType(array $filters, ?int $limit = null): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $knownTypes = array_keys(PropertyService::TYPES);
        $sql = "SELECT CASE WHEN p.type IN (" . $this->placeholders(count($knownTypes)) . ") THEN p.type ELSE 'unknown' END AS type,
                COUNT(DISTINCT l.id) AS lead_count
            FROM properties p JOIN lead_properties lp ON lp.property_id = p.id JOIN leads l ON l.id = lp.lead_id
            {$where} GROUP BY type ORDER BY lead_count DESC, type ASC";
        // The fixed catalog values are bound; values from the request never enter SQL text.
        if ($limit !== null) { $sql .= ' LIMIT :limit'; }
        $statement = $this->pdo->prepare($sql);
        $bound = [];
        foreach ($knownTypes as $i => $type) $bound[':known_type_' . $i] = [$type, PDO::PARAM_STR];
        foreach ($params as $key => $value) $bound[$key] = [$value, PDO::PARAM_STR];
        if ($limit !== null) $bound[':limit'] = [$limit, PDO::PARAM_INT];
        $this->execute($statement, [], $bound);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $key = (string)$row['type'];
            $row = ['type' => $key, 'label' => $key === 'unknown' ? 'Tipo não identificado' : (PropertyService::TYPES[$key] ?? $key), 'lead_count' => (int)$row['lead_count']];
        }
        unset($row);
        return $rows;
    }

    /** @param array<string,mixed> $filters @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
    public function paginateProperties(array $filters, int $page, int $perPage = 15): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM (
            SELECT p.id FROM properties p JOIN lead_properties lp ON lp.property_id=p.id JOIN leads l ON l.id=lp.lead_id {$where} GROUP BY p.id
        ) ranked");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $query = $this->pdo->prepare("SELECT p.id, p.code, p.title, p.type, p.status, COUNT(DISTINCT l.id) AS lead_count
            FROM properties p JOIN lead_properties lp ON lp.property_id=p.id JOIN leads l ON l.id=lp.lead_id {$where}
            GROUP BY p.id ORDER BY lead_count DESC, p.code COLLATE NOCASE ASC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $query->bindValue($key, $value, PDO::PARAM_STR);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        return ['items' => $query->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** Leads matching the current association and the same date/stage filters. */
    public function paginatePropertyLeads(int $propertyId, array $filters, int $page, int $perPage = 15): array
    {
        $filters['property_id'] = $propertyId;
        [$where, $params] = $this->buildWhere($filters);
        $count = $this->pdo->prepare("SELECT COUNT(DISTINCT l.id) FROM properties p JOIN lead_properties lp ON lp.property_id=p.id JOIN leads l ON l.id=lp.lead_id {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $query = $this->pdo->prepare("SELECT DISTINCT l.id, l.name, l.stage, l.created_at FROM properties p
            JOIN lead_properties lp ON lp.property_id=p.id JOIN leads l ON l.id=lp.lead_id {$where}
            ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $query->bindValue($key, $value, PDO::PARAM_STR);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) $item['created_at_local'] = (new DateTimeImmutable((string)$item['created_at'], new DateTimeZone('UTC')))->setTimezone(DateHelper::getTimezone())->format('Y-m-d H:i:s');
        unset($item);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @param array<string,mixed> $filters @return array{string,array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];
        if (($filters['date_from_utc'] ?? null) !== null) { $clauses[] = 'l.created_at >= :date_from'; $params[':date_from'] = $filters['date_from_utc']; }
        if (($filters['date_to_exclusive_utc'] ?? null) !== null) { $clauses[] = 'l.created_at < :date_to'; $params[':date_to'] = $filters['date_to_exclusive_utc']; }
        if (($filters['stage'] ?? null) !== null) { $clauses[] = 'l.stage = :stage'; $params[':stage'] = $filters['stage']; }
        if (($filters['status'] ?? null) !== null) { $clauses[] = 'p.status = :status'; $params[':status'] = $filters['status']; }
        if (($filters['property_id'] ?? null) !== null) { $clauses[] = 'p.id = :property_id'; $params[':property_id'] = (int)$filters['property_id']; }
        $type = $filters['type'] ?? null;
        if ($type === 'unknown') {
            $known = array_keys(PropertyService::TYPES);
            $clauses[] = 'p.type NOT IN (' . $this->placeholders(count($known)) . ')';
            foreach ($known as $i => $value) $params[':known_type_' . $i] = $value;
        } elseif ($type !== null) {
            $clauses[] = 'p.type = :type';
            $params[':type'] = $type;
        }
        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    private function placeholders(int $count): string
    {
        return implode(',', array_map(static fn($i) => ':known_type_' . $i, range(0, $count - 1)));
    }

    /** @param array<string,mixed> $params @param array<string,array{mixed,int}> $extra */
    private function execute(\PDOStatement $statement, array $params, array $extra = []): void
    {
        foreach ($params as $key => $value) $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        foreach ($extra as $key => [$value, $type]) $statement->bindValue($key, $value, $type);
        $statement->execute();
    }
}
