<?php

declare(strict_types=1);

namespace App\Auth\Session;

final class SessionManager
{
    private static bool $started = false;

    /**
     * Inicia a sessão com diretrizes reforçadas de segurança e persistência no SQLite.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $lifetime = (int)($_ENV['SESSION_LIFETIME'] ?? 28800);
        $cookieName = $_ENV['SESSION_COOKIE_NAME'] ?? 'pinciara_session';
        $isSecure = filter_var($_ENV['SESSION_SECURE_COOKIE'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_name($cookieName);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_set_save_handler(new DatabaseSessionHandler($lifetime), true);

        if (!session_start()) {
            throw new \RuntimeException('Falha ao inicializar a sessão do sistema.');
        }

        self::$started = true;
    }

    /**
     * Regenera o ID da sessão para prevenir Session Fixation.
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Destrói a sessão atual e remove o cookie do navegador.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
            self::$started = false;
        }
    }

    /**
     * Define uma mensagem flash (ex: 'success', 'error', 'info', 'warning').
     */
    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$type][] = $message;
    }

    /**
     * Recupera e consome mensagens flash do tipo especificado (ou todas).
     *
     * @return array<string, list<string>>|list<string>
     */
    public static function getFlashes(?string $type = null): array
    {
        self::start();

        if ($type !== null) {
            $messages = $_SESSION['_flash'][$type] ?? [];
            unset($_SESSION['_flash'][$type]);
            return $messages;
        }

        $all = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $all;
    }
}
