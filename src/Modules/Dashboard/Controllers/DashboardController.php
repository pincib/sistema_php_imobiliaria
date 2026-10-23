<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Leads\Services\FollowUpService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DashboardController
{
    private Renderer $renderer;
    private FollowUpService $followUpService;

    public function __construct(
        ?Renderer $renderer = null,
        ?FollowUpService $followUpService = null
    ) {
        $this->renderer = $renderer ?? new Renderer();
        $this->followUpService = $followUpService ?? new FollowUpService();
    }

    /**
     * Dashboard principal com contatos do dia, atrasados, futuros e métricas operacionais.
     */
    public function index(Request $request, Response $response): Response
    {
        $currentUser = AuthService::user() ?? [];
        $queryParams = $request->getQueryParams();

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
        ]);

        // Se requisição HTMX para atualizar apenas o container da agenda de retornos
        if ($request->hasHeader('HX-Request') && ($queryParams['partial'] ?? '') === 'follow-ups') {
            $html = $this->renderer->render('dashboard/partials/follow_ups_container', $viewData, false);
            $response->getBody()->write($html);
            return $response;
        }

        $html = $this->renderer->render('dashboard/index', $viewData);
        $response->getBody()->write($html);
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
