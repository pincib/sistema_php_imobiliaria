<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Session\SessionManager;
use App\Security\Csrf;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string> Métodos HTTP que realizam mutação de estado e exigem CSRF
     */
    private const MUTATING_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    public function process(Request $request, RequestHandler $handler): Response
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, self::MUTATING_METHODS, true)) {
            $parsedBody = $request->getParsedBody();
            $token = null;

            // Busca token no body
            if (is_array($parsedBody) && isset($parsedBody['_csrf_token'])) {
                $token = (string)$parsedBody['_csrf_token'];
            }

            // Fallback para cabeçalho HTTP (ex: HTMX ou fetch/AJAX)
            if (empty($token)) {
                $tokenHeader = $request->getHeaderLine('X-CSRF-Token');
                if (!empty($tokenHeader)) {
                    $token = $tokenHeader;
                }
            }

            if (!Csrf::validate($token)) {
                $isHtmx = $request->hasHeader('HX-Request');
                $acceptsJson = str_contains($request->getHeaderLine('Accept'), 'application/json');

                $response = new SlimResponse();

                if ($isHtmx || $acceptsJson) {
                    $response->getBody()->write(json_encode([
                        'error' => 'Token CSRF inválido ou expirado. Por favor, recarregue a página.',
                    ], JSON_UNESCAPED_UNICODE));

                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(403);
                }

                SessionManager::flash('error', 'Token de segurança expirado ou inválido. Por favor, tente novamente.');

                return $response
                    ->withHeader('Location', $request->getHeaderLine('Referer') ?: '/login')
                    ->withStatus(302);
            }
        }

        return $handler->handle($request);
    }
}
