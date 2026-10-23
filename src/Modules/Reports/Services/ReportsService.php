<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Database\Connection;
use App\Modules\Leads\Pipeline\PipelineManager;
use DateTimeImmutable;
use PDO;

final class ReportsService
{
    public const MAX_PERIOD_DAYS = 366;
    public const MAX_EXPORT_ROWS = 5000;
    public const DETAIL_ROWS = 25;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /** @return array{filters: array{from: string, to: string, stage: string, user_id: string}, errors: list<string>} */
    public function normalizeFilters(array $input): array
    {
        $today = new DateTimeImmutable('today');
        $from = trim((string)($input['from'] ?? $today->modify('-29 days')->format('Y-m-d')));
        $to = trim((string)($input['to'] ?? $today->format('Y-m-d')));
        $stage = trim((string)($input['stage'] ?? ''));
        $userId = trim((string)($input['user_id'] ?? ''));
        $errors = [];

        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $errors[] = 'Informe as datas no formato válido.';
                $from = $today->modify('-29 days')->format('Y-m-d');
                $to = $today->format('Y-m-d');
                break;
            }
        }

        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        if ($start > $end) {
            $errors[] = 'A data inicial deve ser anterior ou igual à data final.';
            $from = $today->modify('-29 days')->format('Y-m-d');
            $to = $today->format('Y-m-d');
            $start = new DateTimeImmutable($from);
            $end = new DateTimeImmutable($to);
        }
        if ((int)$start->diff($end)->days + 1 > self::MAX_PERIOD_DAYS) {
            $errors[] = 'O período máximo para consulta é de ' . self::MAX_PERIOD_DAYS . ' dias.';
            $from = $end->modify('-' . (self::MAX_PERIOD_DAYS - 1) . ' days')->format('Y-m-d');
        }

        if ($stage !== '' && !PipelineManager::isValidStage($stage)) {
            $errors[] = 'O estágio selecionado não é válido.';
            $stage = '';
        }
        if ($userId !== '' && $userId !== 'unassigned' && (!ctype_digit($userId) || (int)$userId < 1)) {
            $errors[] = 'O corretor selecionado não é válido.';
            $userId = '';
        }

        return ['filters' => ['from' => $from, 'to' => $to, 'stage' => $stage, 'user_id' => $userId], 'errors' => $errors];
    }

    /** @return array<string, mixed> */
    public function getReport(array $filters): array
    {
        [$where, $params] = $this->leadConditions($filters);
        $createdParams = $params + [':created_from' => $filters['from'] . ' 00:00:00', ':created_to' => $this->dayAfter($filters['to'])];
        $interactionParams = $params + [':contacted_from' => $filters['from'] . ' 00:00:00', ':contacted_to' => $this->dayAfter($filters['to'])];

        $created = $this->scalar(
            "SELECT COUNT(*) FROM leads l WHERE {$where} AND l.created_at >= :created_from AND l.created_at < :created_to",
            $createdParams
        );
        // Pipeline is a current snapshot: date range is intentionally not applied.
        $stageRows = $this->all(
            "SELECT l.stage, COUNT(*) AS total FROM leads l WHERE {$where} GROUP BY l.stage ORDER BY l.stage",
            $params
        );
        $stages = array_fill_keys(array_keys(PipelineManager::STAGES), 0);
        foreach ($stageRows as $row) {
            $stages[$row['stage']] = (int)$row['total'];
        }

        $byType = $this->all(
            "SELECT i.type, COUNT(*) AS total FROM lead_interactions i JOIN leads l ON l.id = i.lead_id WHERE {$where} AND i.contacted_at >= :contacted_from AND i.contacted_at < :contacted_to GROUP BY i.type ORDER BY total DESC, i.type",
            $interactionParams
        );
        // The grouped scan already covers every matching interaction, so derive
        // the headline total here instead of scanning the same date range again.
        $interactions = array_sum(array_map(static fn (array $row): int => (int)$row['total'], $byType));
        $interactionDetails = $this->all(
            "SELECT i.id, i.lead_id, l.name AS lead_name, i.type, i.contacted_at, u.name AS actor
             FROM lead_interactions i JOIN leads l ON l.id = i.lead_id
             LEFT JOIN users u ON u.id = i.user_id
             WHERE {$where} AND i.contacted_at >= :contacted_from AND i.contacted_at < :contacted_to
             ORDER BY i.contacted_at DESC, i.id DESC LIMIT " . self::DETAIL_ROWS,
            $interactionParams
        );

        $details = $this->all(
            "SELECT l.id, l.name, l.email, l.phone, l.source, l.stage, l.created_at, u.name AS broker,
                (SELECT COUNT(*) FROM lead_interactions i WHERE i.lead_id = l.id AND i.contacted_at >= :contacted_from AND i.contacted_at < :contacted_to) AS interactions_in_period
             FROM leads l LEFT JOIN users u ON u.id = l.user_id
             WHERE {$where} AND l.created_at >= :created_from AND l.created_at < :created_to
             ORDER BY l.created_at DESC, l.id DESC LIMIT " . self::DETAIL_ROWS,
            $createdParams + [':contacted_from' => $filters['from'] . ' 00:00:00', ':contacted_to' => $this->dayAfter($filters['to'])]
        );

        return [
            'created_total' => $created,
            'interactions_total' => $interactions,
            'stage_counts' => $stages,
            'interactions_by_type' => $byType,
            'interaction_details' => $interactionDetails,
            'lead_details' => $details,
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, truncated: bool} */
    public function getExportRows(array $filters, string $dataset): array
    {
        [$where, $params] = $this->leadConditions($filters);
        $limit = self::MAX_EXPORT_ROWS + 1;
        if ($dataset === 'interactions') {
            $rows = $this->all(
                "SELECT i.id AS interaction_id, i.lead_id, l.name AS lead_name, i.type, i.contacted_at, u.name AS actor
                 FROM lead_interactions i JOIN leads l ON l.id = i.lead_id
                 LEFT JOIN users u ON u.id = i.user_id
                 WHERE {$where} AND i.contacted_at >= :contacted_from AND i.contacted_at < :contacted_to
                 ORDER BY i.contacted_at DESC, i.id DESC LIMIT {$limit}",
                $params + [':contacted_from' => $filters['from'] . ' 00:00:00', ':contacted_to' => $this->dayAfter($filters['to'])]
            );
        } elseif ($dataset === 'portfolio') {
            $rows = $this->all(
                "SELECT l.id AS lead_id, l.name, l.email, l.phone, l.stage, l.created_at, u.name AS broker
                 FROM leads l LEFT JOIN users u ON u.id = l.user_id
                 WHERE {$where} ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit}",
                $params
            );
        } else {
            $rows = $this->all(
                "SELECT l.id AS lead_id, l.name, l.email, l.phone, l.source, l.stage, l.created_at, u.name AS broker,
                    (SELECT COUNT(*) FROM lead_interactions i WHERE i.lead_id = l.id AND i.contacted_at >= :contacted_from AND i.contacted_at < :contacted_to) AS interactions_in_period
                 FROM leads l LEFT JOIN users u ON u.id = l.user_id
                 WHERE {$where} AND l.created_at >= :created_from AND l.created_at < :created_to
                 ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit}",
                $params + [
                    ':created_from' => $filters['from'] . ' 00:00:00',
                    ':created_to' => $this->dayAfter($filters['to']),
                    ':contacted_from' => $filters['from'] . ' 00:00:00',
                    ':contacted_to' => $this->dayAfter($filters['to']),
                ]
            );
        }
        $truncated = count($rows) > self::MAX_EXPORT_ROWS;
        return ['rows' => $truncated ? [] : $rows, 'truncated' => $truncated];
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function leadConditions(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($filters['stage'] !== '') {
            $where[] = 'l.stage = :stage';
            $params[':stage'] = $filters['stage'];
        }
        if ($filters['user_id'] !== '') {
            if ($filters['user_id'] === 'unassigned') {
                $where[] = 'l.user_id IS NULL';
            } else {
                $where[] = 'l.user_id = :user_id';
                $params[':user_id'] = (int)$filters['user_id'];
            }
        }
        return [implode(' AND ', $where), $params];
    }

    private function dayAfter(string $date): string
    {
        return (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }

    private function scalar(string $sql, array $params): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    private function all(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
