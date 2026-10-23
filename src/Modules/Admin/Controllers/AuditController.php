<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Audit\AuditService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuditController
{
    private Renderer $renderer;

    public function __construct(?Renderer $renderer = null)
    {
        $this->renderer = $renderer ?? new Renderer();
    }

    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $action = !empty($queryParams['action']) ? trim((string)$queryParams['action']) : null;

        $result = AuditService::paginate(page: $page, perPage: 20, action: $action);

        $html = $this->renderer->render('admin/audit', [
            'title' => 'Auditoria de Segurança e Movimentações',
            'logs' => $result['data'],
            'total' => $result['total'],
            'currentPage' => $page,
            'perPage' => 20,
            'currentAction' => $action,
        ]);

        $response->getBody()->write($html);
        return $response;
    }
}
