<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        SessionManager::start();

        if (!AuthService::check()) {
            $isHtmx = $request->hasHeader('HX-Request');
            $acceptsJson = str_contains($request->getHeaderLine('Accept'), 'application/json');

            $response = new SlimResponse();

            if ($isHtmx) {
                // Instrução nativa do HTMX para redirecionar no lado do cliente
                return $response
                    ->withHeader('HX-Redirect', '/login')
                    ->withStatus(401);
            }

            if ($acceptsJson) {
                $response->getBody()->write(json_encode([
                    'error' => 'Não autenticado. Sessão expirada ou inválida.',
                ], JSON_UNESCAPED_UNICODE));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(401);
            }

            // Armazena URL de destino para redirecionamento pós-login
            $_SESSION['_intended_url'] = (string)$request->getUri();
            SessionManager::flash('error', 'Por favor, faça login para continuar.');

            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
