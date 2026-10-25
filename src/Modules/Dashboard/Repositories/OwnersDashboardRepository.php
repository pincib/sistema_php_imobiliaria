<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Repositories;

use App\Database\Connection;
use App\Modules\Properties\Services\PropertyService;
use PDO;

/** Read-only queries over current owner_properties associations and owner contact logs. */
final class OwnersDashboardRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    public function topOwners(string $ranking, array $filters, int $limit = 3): array
    {
        [$sql, $params] = $this->aggregateQuery($ranking, $filters);
        $statement = $this->pdo->prepare($sql . ' ORDER BY metric_value DESC, o.name COLLATE NOCASE ASC LIMIT :limit');
        $this->execute($statement, $params, [':limit' => [$limit, PDO::PARAM_INT]]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $filters @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
    public function paginateOwners(string $ranking, array $filters, int $page, int $perPage = 15): array
    {
        [$sql, $params] = $this->aggregateQuery($ranking, $filters);
        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') ranked';
        $count = $this->pdo->prepare($countSql);
        $this->execute($count, $params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $query = $this->pdo->prepare($sql . ' ORDER BY metric_value DESC, o.name COLLATE NOCASE ASC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $query->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        return ['items' => $query->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @param array<string,mixed> $filters @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
    public function paginateContributions(int $ownerId, string $ranking, array $filters, int $page, int $perPage = 15): array
    {
        $eligibilityFilters = $filters;
        $eligibilityFilters['owner_id'] = $ownerId;
        [$eligibleSql, $eligibleParams] = $this->aggregateQuery($ranking, $eligibilityFilters);
        $eligible = $this->pdo->prepare($eligibleSql);
        $eligible->execute($eligibleParams);
        if (!$eligible->fetch(PDO::FETCH_ASSOC)) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 1];
        }
        [$propertyWhere, $params] = $this->propertyWhere($filters, $ranking);
        if ($ranking === 'count' && ($filters['intention'] ?? null) !== null) {
            $propertyWhere .= ' AND p.intention = :intention';
            $params[':intention'] = $filters['intention'];
        }
        $params[':owner_id'] = $ownerId;
        $moneyColumn = $ranking === 'sale' ? 'p.sale_price' : 'p.rent_price';
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM owner_properties op JOIN properties p ON p.id=op.property_id
            WHERE op.owner_id=:owner_id {$propertyWhere}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $selectValue = $ranking === 'count' ? 'NULL AS contribution_value' : "{$moneyColumn} AS contribution_value";
        $query = $this->pdo->prepare("SELECT p.id AS property_id, p.code, p.title, p.type, p.status, p.intention, {$selectValue}
            FROM owner_properties op JOIN properties p ON p.id=op.property_id
            WHERE op.owner_id=:owner_id {$propertyWhere} ORDER BY p.code COLLATE NOCASE ASC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $query->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        return ['items' => $query->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    public function latestConfirmedOwnerContactUtc(): ?string
    {
        $statement = $this->pdo->prepare("SELECT created_at FROM whatsapp_contact_logs
            WHERE contact_type = :contact_type AND result = :result ORDER BY created_at DESC, id DESC LIMIT 1");
        $statement->execute([':contact_type' => 'owner', ':result' => 'confirmado pelo corretor']);
        $value = $statement->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    public function ownerName(int $ownerId): ?string
    {
        $statement = $this->pdo->prepare('SELECT name FROM owners WHERE id = :id');
        $statement->execute([':id' => $ownerId]);
        $name = $statement->fetchColumn();
        return $name === false ? null : (string)$name;
    }

    /** @param array<string,mixed> $filters @return array{string,array<string,mixed>} */
    private function aggregateQuery(string $ranking, array $filters): array
    {
        if (!in_array($ranking, ['count', 'sale', 'rent'], true)) throw new \InvalidArgumentException('Ranking de proprietário inválido.');
        [$where, $params] = $this->propertyWhere($filters, $ranking);
        if ($ranking === 'count' && ($filters['intention'] ?? null) !== null) {
            $where .= ' AND p.intention = :intention';
            $params[':intention'] = $filters['intention'];
        }
        $metric = match ($ranking) {
            'count' => 'COUNT(DISTINCT p.id)',
            'sale' => 'SUM(p.sale_price)',
            'rent' => 'SUM(p.rent_price)',
        };
        $ownerFilter = '';
        if (($filters['owner_id'] ?? null) !== null) {
            $ownerFilter = ' AND o.id = :owner_id';
            $params[':owner_id'] = (int)$filters['owner_id'];
        }
        $sql = "SELECT o.id AS owner_id, o.name, {$metric} AS metric_value
            FROM owners o JOIN owner_properties op ON op.owner_id=o.id JOIN properties p ON p.id=op.property_id
            WHERE 1=1 {$where}{$ownerFilter} GROUP BY o.id";
        if ($ranking !== 'count' && (($filters['value_min'] ?? null) !== null || ($filters['value_max'] ?? null) !== null)) {
            $sql .= ' HAVING ' . $metric . ' >= CAST(:value_min AS NUMERIC)';
            $params[':value_min'] = $filters['value_min'] ?? 0;
            if (($filters['value_max'] ?? null) !== null) {
                $sql .= ' AND ' . $metric . ' <= CAST(:value_max AS NUMERIC)';
                $params[':value_max'] = $filters['value_max'];
            }
        }
        return [$sql, $params];
    }

    /** @param array<string,mixed> $filters @return array{string,array<string,mixed>} */
    private function propertyWhere(array $filters, string $ranking): array
    {
        $clauses = [];
        $params = [];
        if (($filters['type'] ?? null) !== null) { $clauses[] = 'p.type = :type'; $params[':type'] = $filters['type']; }
        if (($filters['status'] ?? null) !== null) { $clauses[] = 'p.status = :status'; $params[':status'] = $filters['status']; }
        if (in_array($ranking, ['sale', 'rent'], true)) {
            $intention = $ranking === 'sale' ? ['venda', 'venda_locacao'] : ['locacao', 'venda_locacao'];
            $clauses[] = 'p.intention IN (:eligible_0, :eligible_1)';
            $params[':eligible_0'] = $intention[0];
            $params[':eligible_1'] = $intention[1];
            $clauses[] = $ranking === 'sale' ? 'p.sale_price IS NOT NULL' : 'p.rent_price IS NOT NULL';
        }
        return [$clauses ? 'AND ' . implode(' AND ', $clauses) : '', $params];
    }

    /** @param array<string,mixed> $params @param array<string,array{mixed,int}> $extra */
    private function execute(\PDOStatement $statement, array $params, array $extra = []): void
    {
        foreach ($params as $key => $value) $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : (is_float($value) ? PDO::PARAM_STR : PDO::PARAM_STR));
        foreach ($extra as $key => [$value, $type]) $statement->bindValue($key, $value, $type);
        $statement->execute();
    }
}
