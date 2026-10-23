<?php

declare(strict_types=1);

namespace App\Auth;

use App\Audit\AuditService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use PDO;

final class AuthService
{
    /**
     * Realiza o login de um usuário autenticando via password_verify().
     *
     * @return array{success: bool, error?: string}
     */
    public static function attempt(string $email, string $password): array
    {
        SessionManager::start();

        $email = trim(mb_strtolower($email));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'error' => 'Por favor, preencha o e-mail e a senha.',
            ];
        }

        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('
            SELECT 
                u.id,
                u.name,
                u.email,
                u.password_hash,
                u.is_active,
                r.id as role_id,
                r.name as role_name,
                r.label as role_label
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = :email
            LIMIT 1
        ');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            AuditService::log(
                action: 'auth.failed_login',
                entityType: 'user',
                entityId: null,
                newValues: ['attempted_email' => $email],
                ip: $ip,
                userAgent: $userAgent
            );

            return [
                'success' => false,
                'error' => 'E-mail ou senha incorretos.',
            ];
        }

        if ((int)$user['is_active'] !== 1) {
            AuditService::log(
                action: 'auth.blocked_inactive_login',
                entityType: 'user',
                entityId: (string)$user['id'],
                userId: (int)$user['id'],
                ip: $ip,
                userAgent: $userAgent
            );

            return [
                'success' => false,
                'error' => 'Sua conta está desativada. Consulte a administração.',
            ];
        }

        // Verifica se o hash precisa de atualização (rehash transparente)
        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $updateStmt->execute([':hash' => $newHash, ':id' => $user['id']]);
        }

        // Previne Session Fixation
        SessionManager::regenerate();

        // Armazena credenciais e perfil na sessão
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = (string)$user['name'];
        $_SESSION['user_email'] = (string)$user['email'];
        $_SESSION['user_role'] = (string)$user['role_name'];
        $_SESSION['user_role_label'] = (string)$user['role_label'];
        $_SESSION['logged_in_at'] = time();

        AuditService::log(
            action: 'auth.login',
            entityType: 'user',
            entityId: (string)$user['id'],
            userId: (int)$user['id'],
            ip: $ip,
            userAgent: $userAgent
        );

        return ['success' => true];
    }

    /**
     * Encerra a sessão do usuário com segurança e registra em auditoria.
     */
    public static function logout(): void
    {
        SessionManager::start();

        if (self::check()) {
            $userId = (int)$_SESSION['user_id'];
            AuditService::log(
                action: 'auth.logout',
                entityType: 'user',
                entityId: (string)$userId,
                userId: $userId
            );
        }

        SessionManager::destroy();
    }

    /**
     * Retorna se o usuário está autenticado no momento.
     */
    public static function check(): bool
    {
        SessionManager::start();
        return !empty($_SESSION['user_id']);
    }

    /**
     * Retorna os dados do usuário autenticado na sessão.
     *
     * @return array{id: int, name: string, email: string, role: string, role_label: string}|null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => (int)$_SESSION['user_id'],
            'name' => (string)($_SESSION['user_name'] ?? ''),
            'email' => (string)($_SESSION['user_email'] ?? ''),
            'role' => (string)($_SESSION['user_role'] ?? ''),
            'role_label' => (string)($_SESSION['user_role_label'] ?? ''),
        ];
    }

    /**
     * Verifica se o usuário atual possui um determinado papel ou conjunto de papéis.
     *
     * @param string|list<string> $roles
     */
    public static function hasRole(string|array $roles): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        $roles = (array)$roles;
        return in_array($user['role'], $roles, true);
    }
}
