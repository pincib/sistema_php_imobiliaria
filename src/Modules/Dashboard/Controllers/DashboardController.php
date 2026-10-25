<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Modules\Dashboard\Services\DashboardDataService;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Leads\Services\FollowUpService;
use App\Modules\Properties\Services\PropertyService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DashboardController
{
    private Renderer $renderer;
    private FollowUpService $followUpService;
    private DashboardDataService $dashboardDataService;

    public function __construct(
        ?Renderer $renderer = null,
        ?FollowUpService $followUpService = null,
        ?DashboardDataService $dashboardDataService = null
    ) {
        $this->renderer = $renderer ?? new Renderer();
        $this->followUpService = $followUpService ?? new FollowUpService();
        $this->dashboardDataService = $dashboardDataService ?? new DashboardDataService();
    }

    /**
     * Dashboard principal com contatos do dia, atrasados, futuros e métricas operacionais.
     */
    public function index(Request $request, Response $response): Response
    {
        $currentUser = AuthService::user() ?? [];
        $queryParams = $request->getQueryParams();

        if ($request->hasHeader('HX-Request') && ($queryParams['partial'] ?? '') === 'management-summary') {
            $summaryData = [
                'summaryRetryUrl' => $this->dashboardLocation($request) . '#management-summary',
            ];
            try {
                $summaryData['managementSummary'] = $this->dashboardDataService->principalSummary();
                $summaryData['managementSummaryError'] = null;
            } catch (\Throwable $exception) {
                error_log('Dashboard management summary failed: ' . $exception->getMessage());
                $summaryData['managementSummary'] = null;
                $summaryData['managementSummaryError'] = 'Não foi possível carregar os indicadores gerenciais.';
            }

            $html = $this->renderer->render('dashboard/partials/management_summary', $summaryData, false);
            $response->getBody()->write($html);
            return $response;
        }

        // Obtém dados de follow-up respeitando permissões de perfil
        $followUpData = $this->followUpService->getDashboardViewData($queryParams, $currentUser);

        // Estatísticas gerais
        $pdo = Connection::getPdo();
        $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
        $activeSessions = (int)$pdo->query('SELECT COUNT(*) FROM sessions WHERE last_activity >= ' . (time() - 28800))->fetchColumn();
        $auditEventsCount = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $totalLeads = (int)$pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();

        $viewData = array_merge($followUpData, [
            'title' => 'Dashboard Operacional — Agenda de Retornos & Pipeline',
            'user' => $currentUser,
            'stages' => PipelineManager::STAGES,
            'getStageBadge' => [PipelineManager::class, 'getBadgeClass'],
            'getStageLabel' => [PipelineManager::class, 'getLabel'],
            'stats' => [
                'total_leads' => $totalLeads,
                'active_users' => $userCount,
                'active_sessions' => $activeSessions,
                'audit_events' => $auditEventsCount,
            ],
            'summaryRetryUrl' => $this->dashboardLocation($request) . '#management-summary',
        ]);

        // Se requisição HTMX para atualizar apenas o container da agenda de retornos
        if ($request->hasHeader('HX-Request') && ($queryParams['partial'] ?? '') === 'follow-ups') {
            $html = $this->renderer->render('dashboard/partials/follow_ups_container', $viewData, false);
            $response->getBody()->write($html);
            return $response;
        }

        // Keep the operational agenda and the other metrics available if a summary query fails.
        try {
            $viewData['managementSummary'] = $this->dashboardDataService->principalSummary();
            $viewData['managementSummaryError'] = null;
            $viewData['managementSummaryErrors'] = $viewData['managementSummary']['errors'];
        } catch (\Throwable $exception) {
            error_log('Dashboard management summary failed: ' . $exception->getMessage());
            $viewData['managementSummary'] = null;
            $viewData['managementSummaryError'] = 'Não foi possível carregar os indicadores gerenciais.';
            $viewData['managementSummaryErrors'] = ['summary' => $viewData['managementSummaryError']];
        }

        $html = $this->renderer->render('dashboard/index', $viewData);
        $response->getBody()->write($html);
        return $response;
    }

    public function leads(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $data = array_merge($this->dashboardDataService->leadsDashboard($query), [
            'title' => 'Análise de Leads',
            'user' => AuthService::user() ?? [],
            'stages' => PipelineManager::STAGES,
            'getStageBadge' => [PipelineManager::class, 'getBadgeClass'],
            'getStageLabel' => [PipelineManager::class, 'getLabel'],
            'queryParams' => $query,
        ]);
        $template = $request->hasHeader('HX-Request') ? 'dashboard/partials/leads_content' : 'dashboard/leads';
        $response->getBody()->write($this->renderer->render($template, $data, $request->hasHeader('HX-Request') ? false : 'layouts/main'));
        return $response;
    }

    public function properties(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $data = array_merge($this->dashboardDataService->propertiesDashboard($query), [
            'title' => 'Análise de Imóveis',
            'user' => AuthService::user() ?? [],
            'stages' => PipelineManager::STAGES,
            'types' => PropertyService::TYPES,
            'statuses' => PropertyService::STATUSES,
            'getStageBadge' => [PipelineManager::class, 'getBadgeClass'],
            'getStageLabel' => [PipelineManager::class, 'getLabel'],
            'queryParams' => $query,
        ]);
        $template = $request->hasHeader('HX-Request') ? 'dashboard/partials/properties_content' : 'dashboard/properties';
        $response->getBody()->write($this->renderer->render($template, $data, $request->hasHeader('HX-Request') ? false : 'layouts/main'));
        return $response;
    }

    public function owners(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $data = array_merge($this->dashboardDataService->ownersDashboard($query), [
            'title' => 'Análise de Proprietários',
            'user' => AuthService::user() ?? [],
            'types' => PropertyService::TYPES,
            'statuses' => PropertyService::STATUSES,
            'intentions' => PropertyService::INTENTIONS,
            'queryParams' => $query,
        ]);
        $template = $request->hasHeader('HX-Request') ? 'dashboard/partials/owners_content' : 'dashboard/owners';
        $response->getBody()->write($this->renderer->render($template, $data, $request->hasHeader('HX-Request') ? false : 'layouts/main'));
        return $response;
    }

    /**
     * Conclui um follow-up diretamente a partir do painel da Dashboard.
     */
    public function completeFollowUp(Request $request, Response $response, array $args): Response
    {
        $followUpId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user() ?? [];
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->followUpService->completeFollowUp($followUpId, $body, $actorId);

        $validationErrors = $result['errors'] ?? [];
        $message = $result['error'] ?? (array_values($validationErrors)[0] ?? 'Não foi possível concluir o retorno. Verifique os campos.');
        return $this->respondWithFollowUps(
            $request,
            $response,
            $body,
            (bool)$result['success'],
            $result['success'] ? 'Resultado do contato registrado com sucesso e retorno finalizado!' : $message
        );
    }

    /**
     * Reagenda um follow-up diretamente da Dashboard.
     */
    public function rescheduleFollowUp(Request $request, Response $response, array $args): Response
    {
        $followUpId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user() ?? [];
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->followUpService->rescheduleFollowUp($followUpId, $body, $actorId);

        $validationErrors = $result['errors'] ?? [];
        $message = $result['error'] ?? (array_values($validationErrors)[0] ?? 'Não foi possível reagendar o retorno. Verifique a nova data e o motivo.');
        return $this->respondWithFollowUps(
            $request,
            $response,
            $body,
            (bool)$result['success'],
            $result['success'] ? 'Retorno reagendado com sucesso!' : $message
        );
    }

    /**
     * Retorna o partial atualizado para HTMX e preserva filtros no fallback por formulário.
     *
     * @param array<string, mixed> $body
     */
    private function respondWithFollowUps(Request $request, Response $response, array $body, bool $success, string $message): Response
    {
        if (!$request->hasHeader('HX-Request')) {
            SessionManager::flash($success ? 'success' : 'error', $message);
            return $response
                ->withHeader('Location', $this->dashboardLocation($request))
                ->withStatus(302);
        }

        $currentUser = AuthService::user() ?? [];
        $queryParams = $request->getQueryParams();
        if (array_key_exists('filter_user_id', $queryParams)) {
            $queryParams['user_id'] = $queryParams['filter_user_id'];
            unset($queryParams['filter_user_id']);
        }
        $filterKeys = ['situation', 'stage', 'date_from', 'date_to', 'q', 'partial'];
        $queryParams = array_merge($queryParams, array_intersect_key($body, array_flip($filterKeys)));
        $viewData = array_merge(
            $this->followUpService->getDashboardViewData($queryParams, $currentUser),
            [
                'stages' => PipelineManager::STAGES,
                'getStageBadge' => [PipelineManager::class, 'getBadgeClass'],
                'getStageLabel' => [PipelineManager::class, 'getLabel'],
                'dashboardNotice' => [
                    'type' => $success ? 'success' : 'error',
                    'message' => $message,
                ],
            ]
        );

        $html = $this->renderer->render('dashboard/partials/follow_ups_container', $viewData, false);
        $response->getBody()->write($html);
        return $response;
    }

    private function dashboardLocation(Request $request): string
    {
        $allowedKeys = ['situation', 'filter_user_id', 'user_id', 'stage', 'date_from', 'date_to', 'q'];
        $query = array_intersect_key($request->getQueryParams(), array_flip($allowedKeys));
        if (array_key_exists('filter_user_id', $query)) {
            $query['user_id'] = $query['filter_user_id'];
            unset($query['filter_user_id']);
        }
        $queryString = http_build_query($query);

        return $queryString === '' ? '/' : '/?' . $queryString;
    }
}
