<?php

declare(strict_types=1);

namespace App\Audit;

use App\Database\Connection;
use PDO;

final class AuditService
{
    /**
     * Registra uma ação de auditoria no sistema.
     *
     * @param string $action Identificador da ação (ex: 'auth.login', 'user.create', 'lead.stage_change')
     * @param string|null $entityType Nome da entidade (ex: 'user', 'lead', 'role')
     * @param string|null $entityId ID da entidade afetada
     * @param array<string, mixed>|null $oldValues Dados anteriores (serializados em JSON)
     * @param array<string, mixed>|null $newValues Dados novos (serializados em JSON)
     * @param int|null $userId ID do usuário autor (se omitido, recupera da sessão)
     * @param string|null $ip Endereço IP do cliente
     * @param string|null $userAgent User Agent do navegador
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        try {
            $pdo = Connection::getPdo();

            $resolvedUserId = $userId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            $resolvedIp = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null);
            $resolvedUserAgent = $userAgent ?? substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

            $stmt = $pdo->prepare('
                INSERT INTO audit_logs (
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    old_values,
                    new_values,
                    ip_address,
                    user_agent
                ) VALUES (
                    :user_id,
                    :action,
                    :entity_type,
                    :entity_id,
                    :old_values,
                    :new_values,
                    :ip_address,
                    :user_agent
                )
            ');

            $stmt->execute([
                ':user_id' => $resolvedUserId,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':old_values' => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                ':new_values' => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                ':ip_address' => $resolvedIp,
                ':user_agent' => $resolvedUserAgent,
            ]);
        } catch (\Throwable) {
            // Falhas de auditoria não devem travar a aplicação, mas podem ser logadas se houver logger
        }
    }

    /**
     * Recupera registros de auditoria com paginação e dados do usuário autor.
     *
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public static function paginate(int $page = 1, int $perPage = 25, ?string $action = null): array
    {
        $pdo = Connection::getPdo();
        $offset = max(0, ($page - 1) * $perPage);

        $whereClause = '';
        $params = [];

        if (!empty($action)) {
            $whereClause = 'WHERE a.action = :action';
            $params[':action'] = $action;
        }

        // Total de registros
        $countSql = "SELECT COUNT(*) FROM audit_logs a {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Consulta paginada
        $sql = "
            SELECT 
                a.*,
                u.name as user_name,
                u.email as user_email
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            {$whereClause}
            ORDER BY a.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }
}
