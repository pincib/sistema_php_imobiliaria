<?php

declare(strict_types=1);

namespace App\Modules\Owners\Controllers;

use App\Audit\AuditService;
use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Modules\Leads\Support\LeadNormalizer;
use App\Modules\Properties\Repositories\PropertyRepository;
use App\View\Renderer;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class OwnersController
{
    private Renderer $view;

    public function __construct(?Renderer $view = null)
    {
        $this->view = $view ?? new Renderer();
    }

    private function pdo(): PDO
    {
        return Connection::getPdo();
    }

    private function page(Response $response, string $template, array $data): Response
    {
        $response->getBody()->write($this->view->render($template, $data));
        return $response;
    }

    /**
     * Listagem com abas de "Desatualizados" e "Atualizados" (Critério de 30 dias)
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $q = trim((string)($params['q'] ?? ''));
        $tab = (string)($params['tab'] ?? 'desatualizados');
        if (!in_array($tab, ['desatualizados', 'atualizados', 'todos'], true)) {
            $tab = 'desatualizados';
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $size = 20;
        $like = '%' . $q . '%';
        $phoneDigits = '%' . preg_replace('/\D/', '', $q) . '%';

        $pdo = $this->pdo();

        // 1. Contagens das abas (sempre atualizadas e leves)
        $countDesatualizados = (int)$pdo->query("
            SELECT COUNT(*) FROM owners 
            WHERE (last_verified_at IS NULL OR datetime(last_verified_at) < datetime('now', '-30 days'))
        ")->fetchColumn();

        $countAtualizados = (int)$pdo->query("
            SELECT COUNT(*) FROM owners 
            WHERE (last_verified_at IS NOT NULL AND datetime(last_verified_at) >= datetime('now', '-30 days'))
        ")->fetchColumn();

        $countTotal = (int)$pdo->query("SELECT COUNT(*) FROM owners")->fetchColumn();

        // 2. Cláusula de status conforme a aba selecionada
        $statusClause = '';
        if ($tab === 'desatualizados') {
            $statusClause = " AND (o.last_verified_at IS NULL OR datetime(o.last_verified_at) < datetime('now', '-30 days'))";
        } elseif ($tab === 'atualizados') {
            $statusClause = " AND (o.last_verified_at IS NOT NULL AND datetime(o.last_verified_at) >= datetime('now', '-30 days'))";
        }

        // 3. Contagem total filtrada
        $countSql = "
            SELECT COUNT(DISTINCT o.id) 
            FROM owners o 
            LEFT JOIN owner_properties op ON op.owner_id = o.id 
            LEFT JOIN properties p ON p.id = op.property_id 
            WHERE (:q = '' OR o.name LIKE :name OR o.email LIKE :email OR o.phone LIKE :phone OR p.code LIKE :code)
            {$statusClause}
        ";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute([
            ':q' => $q,
            ':name' => $like,
            ':email' => $like,
            ':phone' => $phoneDigits,
            ':code' => $like,
        ]);
        $total = (int)$stmtCount->fetchColumn();

        $pages = max(1, (int)ceil($total / $size));
        $page = min($page, $pages);

        // 4. Ordenação estratégica:
        $orderBy = match ($tab) {
            'desatualizados' => 'ORDER BY (o.last_verified_at IS NULL) DESC, o.last_verified_at ASC, o.name COLLATE NOCASE ASC',
            'atualizados' => 'ORDER BY o.last_verified_at DESC, o.name COLLATE NOCASE ASC',
            default => 'ORDER BY o.name COLLATE NOCASE ASC',
        };

        // 5. Consulta dos registros paginados
        $listSql = "
            SELECT 
                o.*,
                u.name AS verified_by_name,
                COUNT(DISTINCT op.property_id) AS property_count,
                GROUP_CONCAT(DISTINCT p.code) AS property_codes
            FROM owners o 
            LEFT JOIN owner_properties op ON op.owner_id = o.id 
            LEFT JOIN properties p ON p.id = op.property_id 
            LEFT JOIN users u ON u.id = o.verified_by_user_id
            WHERE (:q = '' OR o.name LIKE :name OR o.email LIKE :email OR o.phone LIKE :phone OR p.code LIKE :code)
            {$statusClause}
            GROUP BY o.id 
            {$orderBy} 
            LIMIT :limit OFFSET :offset
        ";

        $stmtList = $pdo->prepare($listSql);
        $stmtList->bindValue(':q', $q);
        $stmtList->bindValue(':name', $like);
        $stmtList->bindValue(':email', $like);
        $stmtList->bindValue(':phone', $phoneDigits);
        $stmtList->bindValue(':code', $like);
        $stmtList->bindValue(':limit', $size, PDO::PARAM_INT);
        $stmtList->bindValue(':offset', ($page - 1) * $size, PDO::PARAM_INT);
        $stmtList->execute();

        $rawOwners = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        // 6. Enriquece os dados com status de verificação e mensagem pré-construída de WhatsApp
        $now = time();
        $thirtyDaysSec = 30 * 86400;

        $owners = array_map(function (array $owner) use ($now, $thirtyDaysSec) {
            $verifiedAt = !empty($owner['last_verified_at']) ? strtotime($owner['last_verified_at']) : null;
            $isUpdated = false;
            $daysAgo = null;
            $daysRemaining = 0;
            $expiresAt = null;

            if ($verifiedAt !== null) {
                $elapsedSec = $now - $verifiedAt;
                $daysAgo = max(0, (int)floor($elapsedSec / 86400));
                if ($elapsedSec <= $thirtyDaysSec) {
                    $isUpdated = true;
                    $daysRemaining = max(1, 30 - $daysAgo);
                    $expiresAt = date('d/m/Y', $verifiedAt + $thirtyDaysSec);
                }
            }

            $owner['is_updated'] = $isUpdated;
            $owner['verified_days_ago'] = $daysAgo;
            $owner['days_remaining'] = $daysRemaining;
            $owner['expires_at'] = $expiresAt;
            $owner['verified_at_formatted'] = $verifiedAt !== null ? date('d/m/Y H:i', $verifiedAt) : null;

            // Gera mensagem automática para o WhatsApp direto da lista
            $firstName = explode(' ', trim($owner['name']))[0];
            $codes = !empty($owner['property_codes']) ? explode(',', $owner['property_codes']) : [];
            if (count($codes) === 1) {
                $msg = "Olá, {$firstName}! Tudo bem? Estou fazendo a atualização mensal do seu imóvel ({$codes[0]}). Você confirma se ele continua disponível e se houve alguma alteração de valor ou condição? Fico à disposição.";
            } elseif (count($codes) > 1) {
                $msg = "Olá, {$firstName}! Tudo bem? Estou fazendo a atualização mensal dos seus imóveis (" . implode(', ', $codes) . "). Você confirma se continuam disponíveis e se houve alterações nos valores? Fico à disposição.";
            } else {
                $msg = "Olá, {$firstName}! Tudo bem? Estou fazendo a atualização cadastral periódica da nossa carteira de proprietários. Gostaria de confirmar se seus contatos continuam os mesmos. Fico à disposição.";
            }

            $cleanPhone = preg_replace('/\D/', '', $owner['phone'] ?? '');
            $intlPhone = str_starts_with($cleanPhone, '55') ? $cleanPhone : '55' . $cleanPhone;
            $owner['quick_whatsapp_url'] = !empty($cleanPhone) ? 'https://wa.me/' . $intlPhone . '?text=' . rawurlencode($msg) : '';

            return $owner;
        }, $rawOwners);

        return $this->page($response, 'owners/index', [
            'title' => 'Gestão de Proprietários',
            'owners' => $owners,
            'q' => $q,
            'tab' => $tab,
            'countDesatualizados' => $countDesatualizados,
            'countAtualizados' => $countAtualizados,
            'countTotal' => $countTotal,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'formatPhone' => [LeadNormalizer::class, 'formatPhone'],
        ]);
    }

    /**
     * Marca o proprietário como "Atualizado" (Renova validade por 30 dias)
     */
    public function markVerified(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $owner = $this->find($id);
        if (!$owner) {
            throw new HttpNotFoundException($request, 'Proprietário não encontrado.');
        }

        $body = (array)($request->getParsedBody() ?? []);
        $returnTo = (string)($body['return_to'] ?? ('/owners/' . $id));
        $actor = (int)(AuthService::user()['id'] ?? 0);

        $stmt = $this->pdo()->prepare('
            UPDATE owners 
            SET last_verified_at = CURRENT_TIMESTAMP, 
                verified_by_user_id = :uid,
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ');
        $stmt->execute([
            ':uid' => $actor > 0 ? $actor : null,
            ':id' => $id,
        ]);

        AuditService::log(
            action: 'owner.verified',
            entityType: 'owner',
            entityId: (string)$id,
            oldValues: ['last_verified_at' => $owner['last_verified_at']],
            newValues: ['last_verified_at' => date('Y-m-d H:i:s'), 'verified_by' => $actor],
            userId: $actor
        );

        SessionManager::flash('success', 'Proprietário "' . $owner['name'] . '" atualizado com sucesso! Validade renovada por 30 dias.');

        return $response->withHeader('Location', $returnTo)->withStatus(302);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->form($response, [], [], '/owners', 'Novo proprietário');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $owner = $this->find((int)$args['id']);
        if (!$owner) {
            return $response->withHeader('Location', '/owners')->withStatus(302);
        }

        return $this->form($response, $owner, [], '/owners/' . $owner['id'], 'Editar proprietário');
    }

    private function form(Response $response, array $owner, array $errors, string $action, string $title): Response
    {
        $stmt = $this->pdo()->query('SELECT id, code, title FROM properties ORDER BY code');
        return $this->page($response, 'owners/form', [
            'title' => $title,
            'owner' => $owner,
            'errors' => $errors,
            'action' => $action,
            'properties' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        return $this->save($request, $response);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return $this->save($request, $response, (int)$args['id']);
    }

    private function save(Request $request, Response $response, ?int $id = null): Response
    {
        $isCreate = $id === null;
        $body = (array)($request->getParsedBody() ?? []);
        $name = LeadNormalizer::sanitizeName((string)($body['name'] ?? ''));
        $email = LeadNormalizer::normalizeEmail((string)($body['email'] ?? ''));
        $phone = LeadNormalizer::normalizePhone((string)($body['phone'] ?? ''));
        $notes = trim((string)($body['notes'] ?? ''));
        $propertySelectionProvided = array_key_exists('property_ids', $body);
        $propertyIds = array_values(array_unique(array_filter(array_map('intval', (array)($body['property_ids'] ?? [])), static fn($i) => $i > 0)));
        $errors = [];

        if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $errors['name'] = 'Informe um nome válido entre 2 e 150 caracteres.';
        }
        if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)) {
            $errors['email'] = 'Informe um e-mail válido de até 150 caracteres.';
        }
        if ($phone !== null && (strlen($phone) < 10 || strlen($phone) > 11)) {
            $errors['phone'] = 'O telefone deve conter DDD e 10 ou 11 dígitos.';
        }
        if (!mb_check_encoding($notes, 'UTF-8') || mb_strlen($notes) > 4000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $notes)) {
            $errors['notes'] = 'As observações devem ter até 4.000 caracteres e não conter caracteres de controle.';
        }

        $pdo = $this->pdo();
        $valid = [];
        if ($propertyIds) {
            $stmtProps = $pdo->prepare('SELECT id, code FROM properties WHERE id IN (' . implode(',', array_fill(0, count($propertyIds), '?')) . ')');
            $stmtProps->execute($propertyIds);
            $valid = $stmtProps->fetchAll(PDO::FETCH_COLUMN);
        }

        if (count($valid) !== count($propertyIds)) {
            $errors['property_ids'] = 'Um ou mais imóveis selecionados não existem.';
        }

        if (!$errors) {
            $dupSql = 'SELECT id, name FROM owners WHERE ' . ($email ? 'email=:email COLLATE NOCASE' : '0') . ($phone ? ' OR phone=:phone' : '') . (!$email && !$phone ? ' OR name=:name COLLATE NOCASE' : '');
            $dupParams = [];
            if ($email) $dupParams[':email'] = $email;
            if ($phone) $dupParams[':phone'] = $phone;
            if (!$email && !$phone) $dupParams[':name'] = $name;

            if ($id) {
                $dupSql = 'SELECT id, name FROM owners WHERE id<>:id AND (' . substr($dupSql, strpos($dupSql, 'WHERE ') + 6) . ')';
                $dupParams[':id'] = $id;
            }

            $stmtDup = $pdo->prepare($dupSql . ' LIMIT 1');
            $stmtDup->execute($dupParams);
            $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);
            if ($dup) {
                $errors['general'] = 'Já existe um proprietário com estes dados (' . $dup['name'] . ' — #' . $dup['id'] . ').';
            }
        }

        if ($errors) {
            return $this->form($response, $body + ['property_ids' => $propertyIds], $errors, $id ? '/owners/' . $id : '/owners', $id ? 'Editar proprietário' : 'Novo proprietário')->withStatus(422);
        }

        $actor = (int)(AuthService::user()['id'] ?? 0);
        $oldValues = null;

        $saved = Connection::transaction(function (PDO $pdo) use (&$id, $isCreate, $propertySelectionProvided, $name, $email, $phone, $notes, $propertyIds, $actor, &$oldValues): bool {
            if ($id) {
                $old = $this->find($id);
                if (!$old) return false;
                $oldLinks = $pdo->prepare('SELECT property_id FROM owner_properties WHERE owner_id=:id ORDER BY property_id');
                $oldLinks->execute([':id' => $id]);
                $oldValues = [
                    'name' => $old['name'],
                    'email' => $old['email'],
                    'phone' => $old['phone'],
                    'notes' => $old['notes'],
                    'property_ids' => array_map('intval', $oldLinks->fetchAll(PDO::FETCH_COLUMN)),
                ];
                $stmt = $pdo->prepare('UPDATE owners SET name=:name, email=:email, phone=:phone, notes=:notes, import_batch_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
                $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':notes' => $notes ?: null, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO owners(name, email, phone, notes) VALUES(:name, :email, :phone, :notes)');
                $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':notes' => $notes ?: null]);
                $id = (int)$pdo->lastInsertId();
            }

            if ($propertySelectionProvided) {
                $link = $pdo->prepare('INSERT INTO owner_properties(owner_id, property_id, linked_by_user_id) VALUES(:owner, :property, :user) ON CONFLICT(owner_id, property_id) DO NOTHING');
                foreach ($propertyIds as $pid) {
                    $link->execute([':owner' => $id, ':property' => $pid, ':user' => $actor]);
                }
                $pdo->prepare('UPDATE owner_properties SET import_batch_id=NULL WHERE owner_id=:owner')->execute([':owner' => $id]);

                if ($propertyIds) {
                    $delete = $pdo->prepare('DELETE FROM owner_properties WHERE owner_id=:owner AND property_id NOT IN (' . implode(',', array_fill(0, count($propertyIds), '?')) . ')');
                    $delete->bindValue(':owner', $id, PDO::PARAM_INT);
                    foreach ($propertyIds as $index => $pid) {
                        $delete->bindValue($index + 1, $pid, PDO::PARAM_INT);
                    }
                    $delete->execute();
                } else {
                    $delete = $pdo->prepare('DELETE FROM owner_properties WHERE owner_id=:owner');
                    $delete->execute([':owner' => $id]);
                }
            }

            AuditService::log(
                $isCreate ? 'owner.create' : 'owner.update',
                'owner',
                (string)$id,
                $oldValues,
                ['name' => $name, 'email' => $email, 'phone' => $phone, 'notes' => $notes ?: null, 'property_ids' => $propertyIds],
                $actor
            );
            return true;
        });

        if (!$saved) {
            return $this->form($response, $body, ['general' => 'Proprietário não encontrado.'], '/owners', 'Editar proprietário')->withStatus(404);
        }

        SessionManager::flash('success', $isCreate ? 'Proprietário cadastrado.' : 'Proprietário atualizado.');
        return $response->withHeader('Location', '/owners/' . $id)->withStatus(302);
    }

    private function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('
            SELECT o.*, u.name AS verified_by_name 
            FROM owners o 
            LEFT JOIN users u ON u.id = o.verified_by_user_id 
            WHERE o.id = :id
        ');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ficha Unificada do Proprietário (com Central de Atualização e WhatsApp integrados)
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $owner = $this->find($id);
        if (!$owner) {
            throw new HttpNotFoundException($request, 'Proprietário não encontrado.');
        }

        // Cálculo de status de atualização (regra dos 30 dias)
        $now = time();
        $thirtyDaysSec = 30 * 86400;
        $verifiedAt = !empty($owner['last_verified_at']) ? strtotime($owner['last_verified_at']) : null;
        $isUpdated = false;
        $daysAgo = null;
        $daysRemaining = 0;
        $expiresAt = null;

        if ($verifiedAt !== null) {
            $elapsedSec = $now - $verifiedAt;
            $daysAgo = max(0, (int)floor($elapsedSec / 86400));
            if ($elapsedSec <= $thirtyDaysSec) {
                $isUpdated = true;
                $daysRemaining = max(1, 30 - $daysAgo);
                $expiresAt = date('d/m/Y', $verifiedAt + $thirtyDaysSec);
            }
        }

        $owner['is_updated'] = $isUpdated;
        $owner['verified_days_ago'] = $daysAgo;
        $owner['days_remaining'] = $daysRemaining;
        $owner['expires_at'] = $expiresAt;
        $owner['verified_at_formatted'] = $verifiedAt !== null ? date('d/m/Y H:i', $verifiedAt) : null;

        // Imóveis vinculados
        $stmtProps = $this->pdo()->prepare('
            SELECT p.*, op.created_at AS linked_at 
            FROM owner_properties op 
            JOIN properties p ON p.id = op.property_id 
            WHERE op.owner_id = :id 
            ORDER BY p.code
        ');
        $stmtProps->execute([':id' => $id]);
        $properties = $stmtProps->fetchAll(PDO::FETCH_ASSOC);

        // Imóveis disponíveis para vincular
        $stmtAvailable = $this->pdo()->prepare('
            SELECT p.id, p.code, p.title 
            FROM properties p 
            WHERE p.id NOT IN (SELECT property_id FROM owner_properties WHERE owner_id = :id) 
            ORDER BY p.code
        ');
        $stmtAvailable->execute([':id' => $id]);
        $available = $stmtAvailable->fetchAll(PDO::FETCH_ASSOC);

        // Histórico recente de interações via WhatsApp
        $stmtLogs = $this->pdo()->prepare("
            SELECT w.*, u.name AS user_name 
            FROM whatsapp_contact_logs w 
            LEFT JOIN users u ON u.id = w.user_id 
            WHERE contact_type = 'owner' AND contact_id = :id 
            ORDER BY w.id DESC 
            LIMIT 10
        ");
        $stmtLogs->execute([':id' => $id]);
        $whatsappHistory = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        // Mensagem de atualização pré-formatada pronta para envio
        $firstName = explode(' ', trim($owner['name']))[0];
        $propertyCount = count($properties);

        if ($propertyCount === 1) {
            $defaultMessage = "Olá, {$firstName}! Tudo bem? Estou fazendo a atualização mensal do seu imóvel: {$properties[0]['code']} — {$properties[0]['title']}. Você confirma se ele continua disponível e se houve alguma alteração de valor ou condição? Fico à disposição.";
            $whatsappRule = 'Atualização mensal — 1 imóvel';
        } elseif ($propertyCount >= 2) {
            $lines = array_map(fn($p) => "• " . $p['code'] . " — " . $p['title'], $properties);
            $defaultMessage = "Olá, {$firstName}! Tudo bem? Estou fazendo a atualização mensal dos seus imóveis:\n" . implode("\n", $lines) . "\nVocê confirma se continuam disponíveis e se houve alguma alteração nos valores ou condições? Fico à disposição.";
            $whatsappRule = 'Atualização mensal — 2 ou mais imóveis';
        } else {
            $defaultMessage = "Olá, {$firstName}! Tudo bem? Estou fazendo a conferência periódica da nossa carteira de proprietários. Gostaria de confirmar se seus contatos continuam os mesmos e se tem algum imóvel para colocar à venda ou locação. Fico à disposição.";
            $whatsappRule = 'Atualização mensal — cadastro';
        }

        $cleanPhone = preg_replace('/\D/', '', $owner['phone'] ?? '');
        $intlPhone = str_starts_with($cleanPhone, '55') ? $cleanPhone : '55' . $cleanPhone;
        $whatsappUrl = !empty($cleanPhone) ? 'https://wa.me/' . $intlPhone . '?text=' . rawurlencode($defaultMessage) : '';

        return $this->page($response, 'owners/show', [
            'title' => $owner['name'] . ' — Ficha do Proprietário',
            'owner' => $owner,
            'properties' => $properties,
            'available' => $available,
            'whatsappHistory' => $whatsappHistory,
            'defaultMessage' => $defaultMessage,
            'whatsappUrl' => $whatsappUrl,
            'whatsappRule' => $whatsappRule,
            'cleanPhone' => $cleanPhone,
            'formatPhone' => [LeadNormalizer::class, 'formatPhone'],
        ]);
    }

    public function associate(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        if (!$this->find($id)) {
            throw new HttpNotFoundException($request, 'Proprietário não encontrado.');
        }

        $body = (array)($request->getParsedBody() ?? []);
        $code = trim((string)($body['property_code'] ?? ''));
        $p = (new PropertyRepository())->findByCode($code);

        if (!$p) {
            SessionManager::flash('error', 'Código de imóvel não encontrado.');
        } else {
            $stmt = $this->pdo()->prepare('INSERT INTO owner_properties(owner_id, property_id, linked_by_user_id) VALUES(:o, :p, :u) ON CONFLICT(owner_id, property_id) DO NOTHING');
            $stmt->execute([':o' => $id, ':p' => $p['id'], ':u' => (int)(AuthService::user()['id'] ?? 0)]);
            if ($stmt->rowCount()) {
                AuditService::log('owner.property_associated', 'owner', (string)$id, null, ['property_id' => (int)$p['id'], 'property_code' => $p['code']], (int)(AuthService::user()['id'] ?? 0));
            }
            SessionManager::flash('success', 'Imóvel ' . $p['code'] . ' vinculado ao proprietário.');
        }
        return $response->withHeader('Location', '/owners/' . $id)->withStatus(302);
    }

    public function disassociate(Request $request, Response $response, array $args): Response
    {
        $ownerId = (int)$args['id'];
        if (!$this->find($ownerId)) {
            throw new HttpNotFoundException($request, 'Proprietário não encontrado.');
        }

        $propertyId = (int)$args['propertyId'];
        $stmtCode = $this->pdo()->prepare('SELECT code FROM properties WHERE id=:id');
        $stmtCode->execute([':id' => $propertyId]);
        $code = $stmtCode->fetchColumn();

        $stmtDelete = $this->pdo()->prepare('DELETE FROM owner_properties WHERE owner_id=:o AND property_id=:p');
        $stmtDelete->execute([':o' => $ownerId, ':p' => $propertyId]);

        if ($stmtDelete->rowCount()) {
            AuditService::log('owner.property_disassociated', 'owner', (string)$ownerId, ['property_id' => $propertyId, 'property_code' => $code], null, (int)(AuthService::user()['id'] ?? 0));
        }

        SessionManager::flash('success', 'Vínculo do imóvel removido.');
        return $response->withHeader('Location', '/owners/' . $ownerId)->withStatus(302);
    }

    public function whatsapp(Request $request, Response $response, array $args): Response
    {
        // Redireciona diretamente para a ficha unificada (/owners/{id}), onde tudo já está integrado
        return $response->withHeader('Location', '/owners/' . (int)$args['id'])->withStatus(302);
    }

    /**
     * Processa a confirmação do WhatsApp e renova a atualização por 30 dias automaticamente
     */
    public function saveWhatsappResult(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $owner = $this->find($id);
        if (!$owner) {
            throw new HttpNotFoundException($request, 'Proprietário não encontrado.');
        }

        $body = (array)($request->getParsedBody() ?? []);
        $stmtCount = $this->pdo()->prepare('SELECT COUNT(*) FROM owner_properties WHERE owner_id=:id');
        $stmtCount->execute([':id' => $id]);
        $propertyCount = (int)$stmtCount->fetchColumn();

        $allowed = match (true) {
            $propertyCount === 1 => ['Atualização mensal — 1 imóvel'],
            $propertyCount >= 2 => ['Atualização mensal — 2 ou mais imóveis'],
            default => ['Atualização mensal — cadastro', 'Atualização mensal — 1 imóvel'],
        };

        $results = [
            'confirmed' => 'confirmado pelo corretor',
            'not_sent' => 'não enviado',
            'cancelled' => 'cancelado',
        ];

        $rule = (string)($body['rule'] ?? ($allowed[0] ?? 'Atualização mensal'));
        $message = trim((string)($body['message'] ?? ''));
        $result = (string)($body['result'] ?? 'confirmed');
        $returnTo = (string)($body['return_to'] ?? ('/owners/' . $id));

        $validEncoding = mb_check_encoding($message, 'UTF-8');
        $hasControlCharacters = $validEncoding && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $message);

        if ($message === '' || !$validEncoding || mb_strlen($message) > 4000 || $hasControlCharacters || !isset($results[$result])) {
            SessionManager::flash('error', 'Mensagem inválida para registro.');
        } else {
            $actor = (int)(AuthService::user()['id'] ?? 0);
            Connection::transaction(function (PDO $pdo) use ($id, $rule, $message, $result, $results, $actor): void {
                $stmt = $pdo->prepare('INSERT INTO whatsapp_contact_logs(contact_type, contact_id, rule, message, result, user_id) VALUES(\'owner\', :id, :rule, :message, :result, :user)');
                $stmt->execute([':id' => $id, ':rule' => $rule, ':message' => $message, ':result' => $results[$result], ':user' => $actor]);

                if ($result === 'confirmed') {
                    // 1. Marca como "contatado" no Kanban
                    $stmtOld = $pdo->prepare('SELECT contact_stage FROM owners WHERE id=:id');
                    $stmtOld->execute([':id' => $id]);
                    $old = $stmtOld->fetchColumn();
                    if ($old !== 'contatado') {
                        $stmtUpdate = $pdo->prepare("UPDATE owners SET contact_stage='contatado', import_batch_id=NULL WHERE id=:id");
                        $stmtUpdate->execute([':id' => $id]);
                        AuditService::log('owner.contact_stage_change', 'owner', (string)$id, ['contact_stage' => $old], ['contact_stage' => 'contatado', 'source' => 'whatsapp'], $actor);
                    }

                    // 2. Renova a verificação para os próximos 30 dias!
                    $stmtVerify = $pdo->prepare('UPDATE owners SET last_verified_at = CURRENT_TIMESTAMP, verified_by_user_id = :uid, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                    $stmtVerify->execute([':uid' => $actor > 0 ? $actor : null, ':id' => $id]);
                    AuditService::log('owner.verified', 'owner', (string)$id, null, ['action' => 'whatsapp_confirmed'], $actor);
                }
            });

            if ($result === 'confirmed') {
                SessionManager::flash('success', '✓ Contato confirmado e proprietário atualizado por mais 30 dias!');
            } else {
                SessionManager::flash('info', 'Status de envio registrado no histórico.');
            }
        }

        return $response->withHeader('Location', $returnTo)->withStatus(302);
    }
}
