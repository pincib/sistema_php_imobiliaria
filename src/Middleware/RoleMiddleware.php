<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Audit\AuditService;
use App\Auth\AuthService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private array $allowedRoles;

    /**
     * @param string|list<string> $allowedRoles
     */
    public function __construct(string|array $allowedRoles)
    {
        $this->allowedRoles = (array)$allowedRoles;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!AuthService::hasRole($this->allowedRoles)) {
            $user = AuthService::user();

            AuditService::log(
                action: 'auth.unauthorized_access_attempt',
                entityType: 'route',
                entityId: (string)$request->getUri()->getPath(),
                newValues: [
                    'user_role' => $user['role'] ?? 'guest',
                    'required_roles' => $this->allowedRoles,
                    'path' => (string)$request->getUri()->getPath(),
                ]
            );

            $response = new SlimResponse();
            $acceptsJson = str_contains($request->getHeaderLine('Accept'), 'application/json');

            if ($acceptsJson) {
                $response->getBody()->write(json_encode([
                    'error' => 'Acesso negado. Seu perfil de usuário não tem permissão para acessar este recurso.',
                ], JSON_UNESCAPED_UNICODE));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }

            $renderer = new Renderer();
            $html = $renderer->render('errors/403', [
                'title' => '403 — Acesso Proibido',
                'message' => 'Seu perfil de usuário não possui permissão para acessar esta área restrita.',
            ]);

            $response->getBody()->write($html);
            return $response->withStatus(403);
        }

        return $handler->handle($request);
    }
}
