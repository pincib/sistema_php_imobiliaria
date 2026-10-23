<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Audit\AuditService;
use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\View\Renderer;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UserController
{
    private Renderer $renderer;

    public function __construct(?Renderer $renderer = null)
    {
        $this->renderer = $renderer ?? new Renderer();
    }

    public function index(Request $request, Response $response): Response
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->query('
            SELECT 
                u.id, 
                u.name, 
                u.email, 
                u.is_active, 
                u.created_at, 
                r.id as role_id, 
                r.name as role_name, 
                r.label as role_label
            FROM users u
            JOIN roles r ON r.id = u.role_id
            ORDER BY u.id ASC
        ');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $roles = $pdo->query('SELECT id, name, label, description FROM roles ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

        $html = $this->renderer->render('admin/users', [
            'title' => 'Gestão de Usuários e Permissões',
            'users' => $users,
            'roles' => $roles,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function create(Request $request, Response $response): Response
    {
        $body = (array)($request->getParsedBody() ?? []);
        $name = trim((string)($body['name'] ?? ''));
        $email = trim(mb_strtolower((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');
        $roleId = (int)($body['role_id'] ?? 2);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            SessionManager::flash('error', 'Dados inválidos. Certifique-se de preencher o nome, um e-mail válido e senha mínima de 8 caracteres.');
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }

        $pdo = Connection::getPdo();

        // Verifica existência
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            SessionManager::flash('error', "O e-mail '{$email}' já está em uso.");
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        Connection::transaction(function (PDO $pdo) use ($name, $email, $hash, $roleId): void {
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, password_hash, role_id, is_active)
                VALUES (:name, :email, :password_hash, :role_id, 1)
            ');
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $hash,
                ':role_id' => $roleId,
            ]);

            $newId = (int)$pdo->lastInsertId();

            AuditService::log(
                action: 'user.create',
                entityType: 'user',
                entityId: (string)$newId,
                newValues: [
                    'name' => $name,
                    'email' => $email,
                    'role_id' => $roleId,
                ]
            );
        });

        SessionManager::flash('success', "Usuário '{$name}' cadastrado com sucesso.");
        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }

    public function updateRole(Request $request, Response $response, array $args): Response
    {
        $userId = (int)($args['id'] ?? 0);
        $body = (array)($request->getParsedBody() ?? []);
        $newRoleId = (int)($body['role_id'] ?? 0);

        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT id, name, role_id FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            SessionManager::flash('error', 'Usuário não encontrado.');
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }

        $oldRoleId = (int)$targetUser['role_id'];

        if ($oldRoleId !== $newRoleId) {
            Connection::transaction(function (PDO $pdo) use ($userId, $newRoleId, $oldRoleId, $targetUser): void {
                $upd = $pdo->prepare('UPDATE users SET role_id = :role_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $upd->execute([':role_id' => $newRoleId, ':id' => $userId]);

                // Aplica a nova permissão imediatamente nas sessões existentes.
                $delSessions = $pdo->prepare('DELETE FROM sessions WHERE user_id = :user_id');
                $delSessions->execute([':user_id' => $userId]);

                AuditService::log(
                    action: 'user.role_change',
                    entityType: 'user',
                    entityId: (string)$userId,
                    oldValues: ['role_id' => $oldRoleId],
                    newValues: ['role_id' => $newRoleId]
                );
            });

            SessionManager::flash('success', "Permissão do usuário '{$targetUser['name']}' atualizada com sucesso.");
        }

        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }

    public function toggleActive(Request $request, Response $response, array $args): Response
    {
        $userId = (int)($args['id'] ?? 0);
        $currentAdmin = AuthService::user();

        if ($currentAdmin !== null && $userId === $currentAdmin['id']) {
            SessionManager::flash('error', 'Você não pode desativar seu próprio usuário.');
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }

        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT id, name, is_active FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            SessionManager::flash('error', 'Usuário não encontrado.');
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }

        $newStatus = (int)$targetUser['is_active'] === 1 ? 0 : 1;

        Connection::transaction(function (PDO $pdo) use ($userId, $newStatus, $targetUser): void {
            $upd = $pdo->prepare('UPDATE users SET is_active = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $upd->execute([':status' => $newStatus, ':id' => $userId]);

            // Se o usuário foi desativado, remove suas sessões ativas imediatamente
            if ($newStatus === 0) {
                $delSessions = $pdo->prepare('DELETE FROM sessions WHERE user_id = :user_id');
                $delSessions->execute([':user_id' => $userId]);
            }

            AuditService::log(
                action: $newStatus === 1 ? 'user.activated' : 'user.deactivated',
                entityType: 'user',
                entityId: (string)$userId,
                oldValues: ['is_active' => (int)$targetUser['is_active']],
                newValues: ['is_active' => $newStatus]
            );
        });

        $msg = $newStatus === 1 ? "Usuário '{$targetUser['name']}' ativado." : "Usuário '{$targetUser['name']}' desativado e deslogado de todas as sessões.";
        SessionManager::flash('success', $msg);

        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }
}
