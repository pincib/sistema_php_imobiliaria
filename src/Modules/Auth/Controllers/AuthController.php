<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    private Renderer $renderer;

    public function __construct(?Renderer $renderer = null)
    {
        $this->renderer = $renderer ?? new Renderer();
    }

    /**
     * Exibe o formulário de login.
     */
    public function showLoginForm(Request $request, Response $response): Response
    {
        SessionManager::start();
        $oldEmail = (string)($_SESSION['_old_email'] ?? '');
        unset($_SESSION['_old_email']);

        $html = $this->renderer->render('auth/login', [
            'title' => 'Entrar — Plataforma Pinciara',
            'oldEmail' => $oldEmail,
        ], 'layouts/auth');

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Processa a autenticação com validação e password_verify().
     */
    public function login(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $result = AuthService::attempt($email, $password);

        if (!$result['success']) {
            SessionManager::start();
            $_SESSION['_old_email'] = $email;
            SessionManager::flash('error', $result['error'] ?? 'Falha na autenticação.');

            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        SessionManager::flash('success', 'Bem-vindo ao CRM Imobiliário!');

        // Redireciona para a URL pretendida antes da autenticação ou para o dashboard
        $intendedUrl = $_SESSION['_intended_url'] ?? '/';
        unset($_SESSION['_intended_url']);

        return $response
            ->withHeader('Location', $intendedUrl)
            ->withStatus(302);
    }

    /**
     * Realiza o logout do usuário.
     */
    public function logout(Request $request, Response $response): Response
    {
        AuthService::logout();
        SessionManager::flash('info', 'Você encerrou sua sessão com segurança.');

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
}
