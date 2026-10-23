<?php

declare(strict_types=1);

namespace App\Modules\Leads\Services;

use App\Audit\AuditService;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Leads\Repositories\LeadRepository;
use App\Modules\Leads\Support\LeadNormalizer;
use InvalidArgumentException;

final class LeadService
{
    private LeadRepository $repository;

    public function __construct(?LeadRepository $repository = null)
    {
        $this->repository = $repository ?? new LeadRepository();
    }

    /**
     * Retorna o repositório de leads.
     */
    public function getRepository(): LeadRepository
    {
        return $this->repository;
    }

    /**
     * Cadastra um novo lead com validação, normalização segura e auditoria.
     *
     * @param array<string, mixed> $input
     * @param int $actorUserId ID do usuário que está realizando o cadastro
     * @return array{success: bool, lead_id?: int, errors?: array<string, string>, duplicate_warning?: string}
     */
    public function createLead(array $input, int $actorUserId): array
    {
        // 1. Validação no servidor
        $errors = LeadNormalizer::validate($input);
        $propertyCode = trim((string)($input['property_code'] ?? ''));
        $propertyId = null;
        if ($propertyCode !== '') {
            $propertyStmt = \App\Database\Connection::getPdo()->prepare('SELECT id FROM properties WHERE code = :code COLLATE NOCASE');
            $propertyStmt->execute([':code' => $propertyCode]);
            $foundPropertyId = $propertyStmt->fetchColumn();
            if ($foundPropertyId === false) $errors['property_code'] = 'O código informado não corresponde a um imóvel cadastrado.';
            else $propertyId = (int)$foundPropertyId;
        }
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 2. Normalização segura dos dados
        $name = LeadNormalizer::sanitizeName((string)$input['name']);
        $email = LeadNormalizer::normalizeEmail($input['email'] ?? null);
        $phone = LeadNormalizer::normalizePhone($input['phone'] ?? null);
        $source = (string)($input['source'] ?? 'manual');
        $notes = !empty($input['notes']) ? trim((string)$input['notes']) : null;
        $assignedUserId = !empty($input['user_id']) ? (int)$input['user_id'] : $actorUserId;

        // 3. Verificação de duplicidade por e-mail ou telefone
        if ($email !== null) {
            $existing = $this->repository->findByEmail($email);
            if ($existing) {
                return [
                    'success' => false,
                    'errors' => ['email' => "Já existe um lead cadastrado com o e-mail '{$email}' (Lead #{$existing['id']} - {$existing['name']})."],
                ];
            }
        }

        if ($phone !== null) {
            $existing = $this->repository->findByPhone($phone);
            if ($existing) {
                return [
                    'success' => false,
                    'errors' => ['phone' => "Já existe um lead cadastrado com o telefone '{$phone}' (Lead #{$existing['id']} - {$existing['name']})."],
                ];
            }
        }

        // 4. Criação no banco
        $nextFollowUpAt = null;
        if (!empty($input['next_follow_up_at'])) {
            try {
                $nextFollowUpAt = \App\Support\DateHelper::fromHtmlDatetimeLocal((string)$input['next_follow_up_at']);
            } catch (\InvalidArgumentException) {
                $nextFollowUpAt = null;
            }
        }

        $leadData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'source' => $source,
            'stage' => PipelineManager::STAGE_NOVO,
            'user_id' => $assignedUserId,
            'notes' => $notes,
            'next_follow_up_at' => $nextFollowUpAt,
        ];

        $leadId = $this->repository->create($leadData);
        if ($propertyId !== null) {
            $this->repository->associateProperty($leadId, $propertyId, 'interesse');
            AuditService::log('lead.property_associated', 'lead', (string)$leadId, null, ['property_id' => $propertyId, 'property_code' => $propertyCode, 'interest_type' => 'interesse'], $actorUserId);
        }

        // Se informou agendamento de retorno, cria o registro correspondente em lead_follow_ups
        if ($nextFollowUpAt !== null) {
            $followUpRepo = new \App\Modules\Leads\Repositories\FollowUpRepository();
            $followUpRepo->create([
                'lead_id' => $leadId,
                'user_id' => $assignedUserId,
                'created_by_user_id' => $actorUserId,
                'scheduled_at' => $nextFollowUpAt,
                'notes' => 'Primeiro retorno programado no cadastro do lead.',
            ]);
        }

        // 5. Auditoria de criação
        AuditService::log(
            action: 'lead.create',
            entityType: 'lead',
            entityId: (string)$leadId,
            oldValues: null,
            newValues: $leadData,
            userId: $actorUserId
        );

        return ['success' => true, 'lead_id' => $leadId];
    }

    /**
     * Atualiza dados cadastrais de um lead com validação, normalização e auditoria.
     *
     * @param int $leadId
     * @param array<string, mixed> $input
     * @param int $actorUserId
     * @return array{success: bool, errors?: array<string, string>}
     */
    public function updateLead(int $leadId, array $input, int $actorUserId): array
    {
        $existing = $this->repository->findById($leadId);
        if (!$existing) {
            return ['success' => false, 'errors' => ['general' => 'Lead não encontrado.']];
        }

        // 1. Validação no servidor
        $errors = LeadNormalizer::validate($input);
        $propertyCode = trim((string)($input['property_code'] ?? ''));
        $propertyId = null;
        if ($propertyCode !== '') {
            $propertyStmt = \App\Database\Connection::getPdo()->prepare('SELECT id FROM properties WHERE code = :code COLLATE NOCASE');
            $propertyStmt->execute([':code' => $propertyCode]);
            $foundPropertyId = $propertyStmt->fetchColumn();
            if ($foundPropertyId === false) $errors['property_code'] = 'O código informado não corresponde a um imóvel cadastrado.';
            else $propertyId = (int)$foundPropertyId;
        }
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 2. Normalização
        $name = LeadNormalizer::sanitizeName((string)$input['name']);
        $email = LeadNormalizer::normalizeEmail($input['email'] ?? null);
        $phone = LeadNormalizer::normalizePhone($input['phone'] ?? null);
        $source = (string)($input['source'] ?? 'manual');
        $notes = !empty($input['notes']) ? trim((string)$input['notes']) : null;

        // 3. Verificação de duplicidade excluindo o próprio lead
        if ($email !== null) {
            $duplicate = $this->repository->findByEmail($email, $leadId);
            if ($duplicate) {
                return [
                    'success' => false,
                    'errors' => ['email' => "Outro lead já está cadastrado com o e-mail '{$email}' (Lead #{$duplicate['id']})."],
                ];
            }
        }

        if ($phone !== null) {
            $duplicate = $this->repository->findByPhone($phone, $leadId);
            if ($duplicate) {
                return [
                    'success' => false,
                    'errors' => ['phone' => "Outro lead já está cadastrado com o telefone '{$phone}' (Lead #{$duplicate['id']})."],
                ];
            }
        }

        $oldValues = [
            'name' => $existing['name'],
            'email' => $existing['email'],
            'phone' => $existing['phone'],
            'source' => $existing['source'],
            'notes' => $existing['notes'],
        ];

        $newValues = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'source' => $source,
            'notes' => $notes,
        ];

        $this->repository->update($leadId, $newValues);
        if ($propertyId !== null) $this->repository->associateProperty($leadId, $propertyId, 'interesse');

        // 4. Auditoria de atualização
        AuditService::log(
            action: 'lead.update',
            entityType: 'lead',
            entityId: (string)$leadId,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: $actorUserId
        );

        return ['success' => true];
    }

    /**
     * Altera o estágio do lead no pipeline, validando transições permitidas e exigindo motivo em caso de perda.
     *
     * @return array{success: bool, error?: string}
     */
    public function changeStage(int $leadId, string $newStage, ?string $lossReason, int $actorUserId): array
    {
        $lead = $this->repository->findById($leadId);
        if (!$lead) {
            return ['success' => false, 'error' => 'Lead não encontrado.'];
        }

        $currentStage = (string)$lead['stage'];

        try {
            PipelineManager::validateTransition($currentStage, $newStage, $lossReason);
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $sanitizedLossReason = $newStage === PipelineManager::STAGE_PERDIDO ? trim((string)$lossReason) : null;

        $this->repository->updateStage($leadId, $newStage, $sanitizedLossReason);

        // Auditoria da mudança de estágio
        AuditService::log(
            action: 'lead.stage_change',
            entityType: 'lead',
            entityId: (string)$leadId,
            oldValues: [
                'stage' => $currentStage,
                'stage_label' => PipelineManager::getLabel($currentStage),
                'loss_reason' => $lead['loss_reason'] ?? null,
            ],
            newValues: [
                'stage' => $newStage,
                'stage_label' => PipelineManager::getLabel($newStage),
                'loss_reason' => $sanitizedLossReason,
            ],
            userId: $actorUserId
        );

        return ['success' => true];
    }

    /**
     * Realiza a troca de responsável do lead com validação de motivo obrigatório e registro auditável.
     *
     * @return array{success: bool, error?: string}
     */
    public function reassignLead(int $leadId, int $newUserId, string $reason, int $actorUserId): array
    {
        $lead = $this->repository->findById($leadId);
        if (!$lead) {
            return ['success' => false, 'error' => 'Lead não encontrado.'];
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            return ['success' => false, 'error' => 'O motivo da troca de responsável é estritamente obrigatório.'];
        }

        if (mb_strlen($cleanReason) < 3) {
            return ['success' => false, 'error' => 'O motivo deve conter ao menos 3 caracteres justificando a reatribuição.'];
        }

        $oldUserId = !empty($lead['user_id']) ? (int)$lead['user_id'] : null;
        if ($oldUserId === $newUserId) {
            return ['success' => false, 'error' => 'O usuário selecionado já é o responsável atual por este lead.'];
        }

        $this->repository->reassign($leadId, $newUserId, $actorUserId, $cleanReason);

        // Auditoria de troca de responsável
        AuditService::log(
            action: 'lead.reassigned',
            entityType: 'lead',
            entityId: (string)$leadId,
            oldValues: [
                'user_id' => $oldUserId,
                'user_name' => $lead['assigned_user_name'] ?? 'Não atribuído',
            ],
            newValues: [
                'user_id' => $newUserId,
                'reason' => $cleanReason,
            ],
            userId: $actorUserId
        );

        return ['success' => true];
    }

    /**
     * Associa um imóvel ao lead (relação N:N).
     *
     * @return array{success: bool, error?: string}
     */
    public function associateProperty(int $leadId, int $propertyId, string $interestType, ?string $notes, int $actorUserId): array
    {
        $lead = $this->repository->findById($leadId);
        if (!$lead) {
            return ['success' => false, 'error' => 'Lead não encontrado.'];
        }

        $pdo = \App\Database\Connection::getPdo();
        $propertyStmt = $pdo->prepare('SELECT id, code FROM properties WHERE id = :id');
        $propertyStmt->execute([':id' => $propertyId]);
        $property = $propertyStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$property) return ['success' => false, 'error' => 'Imóvel não encontrado.'];
        if (!in_array($interestType, ['interesse', 'visitado', 'proposta', 'visita_agendada', 'visita_realizada', 'proposta_em_analise', 'descartado'], true)) return ['success' => false, 'error' => 'Tipo de relação inválido.'];

        $cleanNotes = !empty($notes) ? trim($notes) : null;
        if ($this->repository->getAssociatedProperty($leadId, $propertyId)) return ['success' => true, 'duplicate' => true];
        $this->repository->associateProperty($leadId, $propertyId, $interestType, $cleanNotes);

        AuditService::log(
            action: 'lead.property_associated',
            entityType: 'lead',
            entityId: (string)$leadId,
            oldValues: null,
            newValues: [
                'property_id' => $propertyId,
                'property_code' => $property['code'],
                'interest_type' => $interestType,
                'notes' => $cleanNotes,
            ],
            userId: $actorUserId
        );
        AuditService::log(
            action: 'property.lead_associated',
            entityType: 'property',
            entityId: (string)$propertyId,
            oldValues: null,
            newValues: ['lead_id' => $leadId, 'lead_name' => $lead['name'], 'interest_type' => $interestType, 'notes' => $cleanNotes],
            userId: $actorUserId
        );

        return ['success' => true, 'duplicate' => false];
    }

    /**
     * Remove o vínculo N:N entre um lead e um imóvel.
     */
    public function disassociateProperty(int $leadId, int $propertyId, int $actorUserId): bool
    {
        $existing = $this->repository->getAssociatedProperty($leadId, $propertyId);
        $removed = $this->repository->disassociateProperty($leadId, $propertyId);

        if ($removed && $existing) {
            AuditService::log(
                action: 'lead.property_disassociated',
                entityType: 'lead',
                entityId: (string)$leadId,
                oldValues: ['property_id' => $propertyId, 'property_code' => $existing['code']],
                newValues: null,
                userId: $actorUserId
            );
            AuditService::log(
                action: 'property.lead_disassociated',
                entityType: 'property',
                entityId: (string)$propertyId,
                oldValues: ['lead_id' => $leadId, 'lead_name' => $existing['lead_name'] ?? null, 'property_id' => $propertyId, 'property_code' => $existing['code'], 'interest_type' => $existing['interest_type'], 'notes' => $existing['notes']],
                newValues: null,
                userId: $actorUserId
            );
        }

        return $removed;
    }

    /**
     * Exclui um lead (autorizado apenas para administradores).
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteLead(int $leadId, int $actorUserId, string $actorRole): array
    {
        if ($actorRole !== 'admin') {
            return ['success' => false, 'error' => 'Apenas administradores possuem permissão para excluir leads.'];
        }

        $lead = $this->repository->findById($leadId);
        if (!$lead) {
            return ['success' => false, 'error' => 'Lead não encontrado.'];
        }

        $this->repository->delete($leadId);

        AuditService::log(
            action: 'lead.delete',
            entityType: 'lead',
            entityId: (string)$leadId,
            oldValues: [
                'name' => $lead['name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
                'stage' => $lead['stage'],
            ],
            newValues: null,
            userId: $actorUserId
        );

        return ['success' => true];
    }

    /**
     * Retorna a visão geral para o topo do módulo ou dashboard.
     *
     * @return array{total: int, by_stage: array<string, int>}
     */
    public function getOverview(): array
    {
        return [
            'total' => $this->repository->count(),
            'by_stage' => $this->repository->countByStage(),
        ];
    }
}
