<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Audit\AuditService;
use App\Database\Connection;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Reports\Services\ReportsService;
use App\Auth\Session\SessionManager;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReportsController
{
    private Renderer $renderer;
    private ReportsService $reports;

    public function __construct(?Renderer $renderer = null, ?ReportsService $reports = null)
    {
        $this->renderer = $renderer ?? new Renderer();
        $this->reports = $reports ?? new ReportsService();
    }

    public function index(Request $request, Response $response): Response
    {
        $normalized = $this->reports->normalizeFilters($request->getQueryParams());
        $filters = $normalized['filters'];
        $data = $this->reports->getReport($filters);
        $brokers = Connection::getPdo()->query(
            "SELECT id, name, is_active FROM users ORDER BY name COLLATE NOCASE"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $html = $this->renderer->render('reports/index', [
            'title' => 'Relatórios gerenciais',
            'filters' => $filters,
            'errors' => $normalized['errors'],
            'stages' => PipelineManager::STAGES,
            'brokers' => $brokers,
            'report' => $data,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function export(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $normalized = $this->reports->normalizeFilters($request->getQueryParams());
        $filters = $normalized['filters'];
        $dataset = (string)($query['dataset'] ?? 'leads');
        if (!in_array($dataset, ['leads', 'interactions', 'portfolio'], true)) {
            $dataset = 'leads';
            $normalized['errors'][] = 'O conjunto de dados selecionado não é válido.';
        }
        if ($normalized['errors'] !== []) {
            SessionManager::flash('error', implode(' ', $normalized['errors']));
            return $response->withHeader('Location', '/reports?' . http_build_query($filters))->withStatus(302);
        }

        $export = $this->reports->getExportRows($filters, $dataset);
        if ($export['truncated']) {
            SessionManager::flash('error', 'A consulta encontrou pelo menos ' . (ReportsService::MAX_EXPORT_ROWS + 1) . ' registros. Nenhum arquivo foi gerado, pois o limite é de ' . ReportsService::MAX_EXPORT_ROWS . ' linhas. Reduza o período ou aplique mais filtros.');
            return $response->withHeader('Location', '/reports?' . http_build_query($filters))->withStatus(302);
        }

        AuditService::log(
            action: 'report.export',
            entityType: 'report',
            entityId: 'leads',
            newValues: ['dataset' => $dataset, 'filters' => $filters, 'row_count' => count($export['rows'])]
        );

        $rowCount = count($export['rows']);
        $filename = 'relatorio-' . $dataset . '-' . $filters['from'] . '-a-' . $filters['to'] . '-' . $rowCount . '-registros.csv';
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        $headers = match ($dataset) {
            'interactions' => ['ID da interação', 'ID do lead', 'Lead', 'Tipo', 'Contatado em', 'Registrado por'],
            'portfolio' => ['ID do lead', 'Nome', 'E-mail', 'Telefone', 'Estágio atual', 'Corretor atual', 'Criado em'],
            default => ['ID do lead', 'Nome', 'E-mail', 'Telefone', 'Origem', 'Estágio atual', 'Corretor atual', 'Criado em', 'Interações no período'],
        };
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($export['rows'] as $row) {
            $values = match ($dataset) {
                'interactions' => [
                    (string)$row['interaction_id'], (string)$row['lead_id'], $this->safeCsvCell((string)$row['lead_name']),
                    $this->safeCsvCell(ucfirst(str_replace('_', ' ', (string)$row['type']))),
                    (string)$row['contacted_at'], $this->safeCsvCell((string)($row['actor'] ?? '')),
                ],
                'portfolio' => [
                    (string)$row['lead_id'], $this->safeCsvCell((string)$row['name']),
                    $this->safeCsvCell((string)($row['email'] ?? '')), $this->safeCsvCell((string)($row['phone'] ?? '')),
                    $this->safeCsvCell(PipelineManager::getLabel((string)$row['stage'])),
                    $this->safeCsvCell((string)($row['broker'] ?? 'Não atribuído')), (string)$row['created_at'],
                ],
                default => [
                    (string)$row['lead_id'], $this->safeCsvCell((string)$row['name']),
                    $this->safeCsvCell((string)($row['email'] ?? '')), $this->safeCsvCell((string)($row['phone'] ?? '')),
                    $this->safeCsvCell((string)$row['source']),
                    $this->safeCsvCell(PipelineManager::getLabel((string)$row['stage'])),
                    $this->safeCsvCell((string)($row['broker'] ?? 'Não atribuído')),
                    (string)$row['created_at'], (string)$row['interactions_in_period'],
                ],
            };
            fputcsv($stream, $values, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $response->getBody()->write($csv === false ? '' : $csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('X-Exported-Rows', (string)$rowCount)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F]*[=+@-]/', $value) === 1 ? "'" . $value : $value;
    }
}
