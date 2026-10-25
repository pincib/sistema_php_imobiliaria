<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Repositories;

use App\Database\Connection;
use App\Support\DateHelper;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Read-only, parameterized queries for the shared Leads dashboard. */
final class LeadsDashboardRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /** @return array<string, int> Current-stage counts for the requested creation period. */
    public function stageCounts(?string $fromUtc, ?string $toExclusiveUtc): array
    {
        [$where, $params] = $this->dateWhere($fromUtc, $toExclusiveUtc);
        $statement = $this->pdo->prepare("SELECT l.stage, COUNT(*) AS total FROM leads l {$where} GROUP BY l.stage");
        $statement->execute($params);
        $counts = array_fill_keys(array_keys(\App\Modules\Leads\Pipeline\PipelineManager::STAGES), 0);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string)$row['stage']] = (int)$row['total'];
        }
        return $counts;
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
    public function paginateLeads(?string $fromUtc, ?string $toExclusiveUtc, ?string $stage, int $page, int $perPage = 15): array
    {
        [$where, $params] = $this->dateWhere($fromUtc, $toExclusiveUtc);
        if ($stage !== null) {
            $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'l.stage = :stage';
            $params[':stage'] = $stage;
        }

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM leads l {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $query = $this->pdo->prepare("SELECT l.id, l.name, l.stage, l.user_id, u.name AS user_name, l.created_at
            FROM leads l LEFT JOIN users u ON u.id = l.user_id {$where}
            ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $query->bindValue($key, $value, PDO::PARAM_STR);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) $item['created_at_local'] = $this->localTimestamp((string)$item['created_at']);
        unset($item);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return array<string,int> */
    private function dateWhere(?string $fromUtc, ?string $toExclusiveUtc): array
    {
        $clauses = [];
        $params = [];
        if ($fromUtc !== null) { $clauses[] = 'l.created_at >= :date_from'; $params[':date_from'] = $fromUtc; }
        if ($toExclusiveUtc !== null) { $clauses[] = 'l.created_at < :date_to'; $params[':date_to'] = $toExclusiveUtc; }
        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    private function localTimestamp(string $utcTimestamp): string
    {
        return (new DateTimeImmutable($utcTimestamp, new DateTimeZone('UTC')))->setTimezone(DateHelper::getTimezone())->format('Y-m-d H:i:s');
    }
}
