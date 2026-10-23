<?php

declare(strict_types=1);

namespace App\Auth\Session;

use App\Database\Connection;
use PDO;
use SessionHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    private int $lifetime;

    public function __construct(int $lifetime = 28800)
    {
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->prepare('
                SELECT payload, last_activity 
                FROM sessions 
                WHERE id = :id 
                LIMIT 1
            ');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return '';
            }

            // Se a sessão expirou por inatividade
            if ((int)$row['last_activity'] < (time() - $this->lifetime)) {
                $this->destroy($id);
                return '';
            }

            return (string)$row['payload'];
        } catch (\Throwable) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $pdo = Connection::getPdo();
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $lastActivity = time();

            $stmt = $pdo->prepare('
                INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
                VALUES (:id, :user_id, :ip_address, :user_agent, :payload, :last_activity)
                ON CONFLICT(id) DO UPDATE SET
                    user_id = :user_id,
                    ip_address = :ip_address,
                    user_agent = :user_agent,
                    payload = :payload,
                    last_activity = :last_activity
            ');

            return $stmt->execute([
                ':id' => $id,
                ':user_id' => $userId,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':payload' => $data,
                ':last_activity' => $lastActivity,
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE id = :id');
            return $stmt->execute([':id' => $id]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $pdo = Connection::getPdo();
            $cutoff = time() - $max_lifetime;
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE last_activity < :cutoff');
            $stmt->execute([':cutoff' => $cutoff]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return false;
        }
    }
}
