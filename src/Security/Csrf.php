<?php

declare(strict_types=1);

namespace App\Security;

use App\Auth\Session\SessionManager;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Retorna o token CSRF atual da sessão ou gera um novo token criptograficamente seguro.
     */
    public static function getToken(): string
    {
        SessionManager::start();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Força a renovação do token CSRF da sessão.
     */
    public static function regenerate(): string
    {
        SessionManager::start();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Valida um token recebido em tempo constante para mitigar timing attacks.
     */
    public static function validate(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        SessionManager::start();
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($stored) || empty($stored)) {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * Renderiza o campo de formulário HTML com o token CSRF.
     */
    public static function field(): string
    {
        $token = self::getToken();
        $escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $escaped . '">';
    }
}
