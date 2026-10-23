<?php

declare(strict_types=1);

namespace App\Modules\Leads\Services;

use App\Audit\AuditService;
use App\Database\Connection;
use App\Modules\Leads\Repositories\FollowUpRepository;
use App\Modules\Leads\Repositories\InteractionRepository;
use App\Modules\Leads\Repositories\LeadRepository;
use App\Support\DateHelper;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class FollowUpService
{
    private FollowUpRepository $repository;
    private LeadRepository $leadRepository;
    private InteractionRepository $interactionRepository;

    public function __construct(
        ?FollowUpRepository $repository = null,
        ?LeadRepository $leadRepository = null,
        ?InteractionRepository $interactionRepository = null
    ) {
        $this->repository = $repository ?? new FollowUpRepository();
        $this->leadRepository = $leadRepository ?? new LeadRepository();
        $this->interactionRepository = $interactionRepository ?? new InteractionRepository();
    }

    public function getRepository(): FollowUpRepository
    {
        return $this->repository;
    }

    private function stringInput(array $input, string $key): string
    {
        $value = $input[$key] ?? null;
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function userIdInput(array $input, string $key, int $fallback): int
    {
        $value = $input[$key] ?? null;
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (!is_scalar($value)) {
            return 0;
        }

        $userId = filter_var($value, FILTER_VALIDATE_INT);
        return $userId === false ? 0 : (int)$userId;
    }

    /**
     * Agenda um novo retorno (follow-up) para o lead com auditoria e proteção contra duplo clique.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, follow_up_id?: int, error?: string, errors?: array<string, string>, duplicate?: bool}
     */
    public function scheduleFollowUp(int $leadId, array $input, int $actorUserId): array
    {
        $lead = $this->leadRepository->findById($leadId);
        if (!$lead) {
            return ['success' => false, 'error' => 'Lead não encontrado.'];
        }

        // 1. Idempotência
        $idempotencyKeyInput = $this->stringInput($input, 'idempotency_key');
        $idempotencyKey = $idempotencyKeyInput !== '' ? $idempotencyKeyInput : null;
        if ($idempotencyKey !== null) {
            $existing = $this->repository->findByIdempotencyKey($idempotencyKey);
            if ($existing) {
                return [
                    'success' => true,
                    'follow_up_id' => (int)$existing['id'],
                    'duplicate' => true,
                ];
            }
        }

        // 2. Validação
        $errors = [];
        $scheduledAtInput = $this->stringInput($input, 'scheduled_at');
        if ($scheduledAtInput === '') {
            $errors['scheduled_at'] = 'A data e horário do retorno são obrigatórios.';
        } else {
            try {
                $scheduledAt = DateHelper::fromHtmlDatetimeLocal($scheduledAtInput);
            } catch (InvalidArgumentException $e) {
                $errors['scheduled_at'] = 'Data e horário de retorno inválidos.';
            }
        }

        // Responsável pelo retorno (default para o corretor do lead ou autor)
        $userId = $this->userIdInput($input, 'user_id', (int)($lead['user_id'] ?? $actorUserId));
        if ($userId <= 0) {
            $errors['user_id'] = 'Selecione o corretor responsável pelo retorno.';
        } elseif (!$this->leadRepository->isActiveAssignee($userId)) {
            $errors['user_id'] = 'O responsável selecionado não está ativo. Escolha um usuário ativo.';
        }

        $notesInput = $this->stringInput($input, 'notes');
        $notes = $notesInput !== '' ? $notesInput : null;
        if ($notes !== null && mb_strlen($notes) > 500) {
            $errors['notes'] = 'As observações devem ter no máximo 500 caracteres.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 3. Persistência
        try {
            $followUpId = $this->repository->create([
                'lead_id' => $leadId,
                'user_id' => $userId,
                'created_by_user_id' => $actorUserId,
                'scheduled_at' => $scheduledAt,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (PDOException $e) {
            $existing = $idempotencyKey !== null ? $this->repository->findByIdempotencyKey($idempotencyKey) : null;
            if ($existing !== null) {
                return [
                    'success' => true,
                    'follow_up_id' => (int)$existing['id'],
                    'duplicate' => true,
                ];
            }
            throw $e;
        }

        // 4. Auditoria
        AuditService::log(
            action: 'lead.follow_up_scheduled',
            entityType: 'follow_up',
            entityId: (string)$followUpId,
            oldValues: null,
            newValues: [
                'lead_id' => $leadId,
                'lead_name' => $lead['name'],
                'user_id' => $userId,
                'scheduled_at' => $scheduledAt,
                'notes' => $notes,
            ],
            userId: $actorUserId
        );

        return [
            'success' => true,
            'follow_up_id' => $followUpId,
            'duplicate' => false,
        ];
    }

    /**
     * Conclui um follow-up pendente, registrando o resultado do contato e opcionalmente o próximo retorno.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, error?: string, errors?: array<string, string>, duplicate?: bool}
     */
    public function completeFollowUp(int $followUpId, array $input, int $actorUserId): array
    {
        $followUp = $this->repository->findById($followUpId);
        if (!$followUp) {
            return ['success' => false, 'error' => 'Follow-up não encontrado.'];
        }

        if ($followUp['status'] !== 'pending') {
            return [
                'success' => true,
                'duplicate' => true,
                'error' => 'Este retorno já foi finalizado anteriormente.',
            ];
        }

        // 1. Idempotência
        $idempotencyKeyInput = $this->stringInput($input, 'idempotency_key');
        $idempotencyKey = $idempotencyKeyInput !== '' ? $idempotencyKeyInput : null;
        if ($idempotencyKey !== null) {
            $existingInteraction = $this->interactionRepository->findByIdempotencyKey($idempotencyKey);
            if ($existingInteraction) {
                return ['success' => true, 'duplicate' => true];
            }
        }

        // 2. Validação
        $errors = [];
        $resultNotes = $this->stringInput($input, 'result_notes');
        if ($resultNotes === '') {
            $errors['result_notes'] = 'O resultado do contato é obrigatório para registrar a conclusão.';
        } elseif (mb_strlen($resultNotes) < 2) {
            $errors['result_notes'] = 'Descreva o resultado com ao menos 2 caracteres.';
        } elseif (mb_strlen($resultNotes) > 2000) {
            $errors['result_notes'] = 'O resultado deve ter no máximo 2.000 caracteres.';
        }

        $type = $this->stringInput($input, 'type') ?: 'ligacao';
        if (!array_key_exists($type, InteractionService::ALLOWED_TYPES)) {
            $type = 'ligacao';
        }

        $contactedAtInput = $this->stringInput($input, 'contacted_at');
        $contactedAt = DateHelper::nowString();
        if ($contactedAtInput !== '') {
            try {
                $contactedAt = DateHelper::fromHtmlDatetimeLocal($contactedAtInput);
            } catch (InvalidArgumentException $e) {
                $errors['contacted_at'] = 'Data e horário do contato inválidos.';
            }
        }

        // Validação se o usuário optou por agendar o próximo retorno no mesmo formulário
        $scheduleNext = $this->stringInput($input, 'schedule_next') === '1';
        $nextScheduledAt = null;
        if ($scheduleNext) {
            $nextScheduledAtInput = $this->stringInput($input, 'next_scheduled_at');
            if ($nextScheduledAtInput === '') {
                $errors['next_scheduled_at'] = 'Informe a data e horário para o próximo retorno.';
            } else {
                try {
                    $nextScheduledAt = DateHelper::fromHtmlDatetimeLocal($nextScheduledAtInput);
                } catch (InvalidArgumentException $e) {
                    $errors['next_scheduled_at'] = 'Data/hora do próximo retorno inválida.';
                }
            }

            $nextUserId = $this->userIdInput($input, 'next_user_id', $actorUserId);
            if ($nextUserId <= 0 || !$this->leadRepository->isActiveAssignee($nextUserId)) {
                $errors['next_user_id'] = 'O responsável pelo próximo retorno deve ser um usuário ativo.';
            }

            $nextNotesInput = $this->stringInput($input, 'next_notes');
            $nextNotes = $nextNotesInput !== '' ? $nextNotesInput : null;
            if ($nextNotes !== null && mb_strlen($nextNotes) > 255) {
                $errors['next_notes'] = 'As observações do próximo retorno devem ter no máximo 255 caracteres.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 3. Execução em Transação Atômica
        $leadId = (int)$followUp['lead_id'];
        $nextFollowUpId = null;

        try {
            Connection::transaction(function (PDO $pdo) use (
            $followUpId,
            $leadId,
            $actorUserId,
            $resultNotes,
            $type,
            $contactedAt,
            $idempotencyKey,
            $scheduleNext,
            $nextScheduledAt,
            $input,
            &$nextFollowUpId
        ): void {
            // A. Cria o registro de interação no histórico cronológico
            $interactionId = $this->interactionRepository->create([
                'lead_id' => $leadId,
                'user_id' => $actorUserId,
                'type' => $type,
                'notes' => 'Retorno de follow-up concluído: ' . $resultNotes,
                'contacted_at' => $contactedAt,
                'idempotency_key' => $idempotencyKey,
            ]);

            // B. Conclui o follow-up atual
            $completed = $this->repository->complete($followUpId, [
                'completed_by_user_id' => $actorUserId,
                'result_notes' => $resultNotes,
                'completed_at' => $contactedAt,
                'interaction_id' => $interactionId,
            ]);
            if (!$completed) {
                throw new RuntimeException('follow_up_already_finalized');
            }

            // C. Se solicitado, agenda o próximo retorno
            if ($scheduleNext && $nextScheduledAt !== null) {
                $nextUserId = $this->userIdInput($input, 'next_user_id', $actorUserId);
                $nextNotesInput = $this->stringInput($input, 'next_notes');
                $nextNotes = $nextNotesInput !== '' ? $nextNotesInput : null;

                $nextFollowUpId = $this->repository->create([
                    'lead_id' => $leadId,
                    'user_id' => $nextUserId,
                    'created_by_user_id' => $actorUserId,
                    'scheduled_at' => $nextScheduledAt,
                    'notes' => $nextNotes,
                ]);
            } else {
                $this->repository->syncLeadNextFollowUp($leadId);
            }
            });
        } catch (PDOException $e) {
            $existingInteraction = $idempotencyKey !== null
                ? $this->interactionRepository->findByIdempotencyKey($idempotencyKey)
                : null;
            if ($existingInteraction !== null) {
                return ['success' => true, 'duplicate' => true];
            }
            throw $e;
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'follow_up_already_finalized') {
                return [
                    'success' => true,
                    'duplicate' => true,
                    'error' => 'Este retorno foi concluído em outra sessão.',
                ];
            }
            throw $e;
        }

        // 4. Auditoria
        AuditService::log(
            action: 'lead.follow_up_completed',
            entityType: 'follow_up',
            entityId: (string)$followUpId,
            oldValues: [
                'status' => 'pending',
                'scheduled_at' => $followUp['scheduled_at'],
            ],
            newValues: [
                'status' => 'completed',
                'result_notes' => $resultNotes,
                'completed_at' => $contactedAt,
                'next_follow_up_id' => $nextFollowUpId,
            ],
            userId: $actorUserId
        );

        return ['success' => true];
    }

    /**
     * Reagenda um follow-up pendente com motivo justificável.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, error?: string, errors?: array<string, string>}
     */
    public function rescheduleFollowUp(int $followUpId, array $input, int $actorUserId): array
    {
        $followUp = $this->repository->findById($followUpId);
        if (!$followUp) {
            return ['success' => false, 'error' => 'Follow-up não encontrado.'];
        }

        if ($followUp['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Apenas retornos pendentes podem ser reagendados.'];
        }

        $errors = [];
        $scheduledAtInput = $this->stringInput($input, 'scheduled_at');
        if ($scheduledAtInput === '') {
            $errors['scheduled_at'] = 'Informe a nova data e horário para o retorno.';
        } else {
            try {
                $newScheduledAt = DateHelper::fromHtmlDatetimeLocal($scheduledAtInput);
            } catch (InvalidArgumentException $e) {
                $errors['scheduled_at'] = 'Nova data e horário inválidos.';
            }
        }

        $reason = $this->stringInput($input, 'reason');
        if ($reason === '') {
            $errors['reason'] = 'Informe o motivo do reagendamento.';
        } elseif (mb_strlen($reason) > 255) {
            $errors['reason'] = 'O motivo deve ter no máximo 255 caracteres.';
        }

        $userId = $this->userIdInput($input, 'user_id', (int)$followUp['user_id']);
        if ($userId <= 0 || !$this->leadRepository->isActiveAssignee($userId)) {
            $errors['user_id'] = 'O responsável selecionado não está ativo. Escolha um usuário ativo.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $updatedNotes = $followUp['notes'] ?? '';
        $noteAppend = "[Reagendado por " . DateHelper::format(DateHelper::nowString()) . ": {$reason}]";
        $finalNotes = $updatedNotes !== '' ? $updatedNotes . "\n" . $noteAppend : $noteAppend;

        $updated = $this->repository->reschedule($followUpId, [
            'scheduled_at' => $newScheduledAt,
            'user_id' => $userId,
            'notes' => $finalNotes,
        ]);
        if (!$updated) {
            return [
                'success' => false,
                'error' => 'Este retorno foi finalizado em outra sessão. Atualize a página para ver o estado atual.',
            ];
        }

        AuditService::log(
            action: 'lead.follow_up_rescheduled',
            entityType: 'follow_up',
            entityId: (string)$followUpId,
            oldValues: [
                'scheduled_at' => $followUp['scheduled_at'],
                'user_id' => $followUp['user_id'],
            ],
            newValues: [
                'scheduled_at' => $newScheduledAt,
                'user_id' => $userId,
                'reason' => $reason,
            ],
            userId: $actorUserId
        );

        return ['success' => true];
    }

    /**
     * Prepara os dados consolidados para o painel de follow-ups da Dashboard.
     *
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $currentUser
     * @return array<string, mixed>
     */
    public function getDashboardViewData(array $queryParams, array $currentUser): array
    {
        $userRole = (string)($currentUser['role'] ?? 'corretor');
        $userId = (int)($currentUser['id'] ?? 0);

        // Resolução do escopo do corretor:
        // Se Corretor e user_id não informado na URL, default é 'mine' (seus próprios retornos), mas permite 'all'
        $requestedUserId = $queryParams['user_id'] ?? null;
        $resolvedUserId = null;

        if ($userRole === 'corretor') {
            if ($requestedUserId === null || $requestedUserId === '' || $requestedUserId === 'mine') {
                $resolvedUserId = $userId;
                $brokerFilter = 'mine';
            } elseif ($requestedUserId === 'all') {
                $resolvedUserId = null;
                $brokerFilter = 'all';
            } else {
                $resolvedUserId = (int)$requestedUserId;
                $brokerFilter = (string)$resolvedUserId;
            }
        } else {
            // Admin default é 'all'
            if ($requestedUserId === null || $requestedUserId === '' || $requestedUserId === 'all') {
                $resolvedUserId = null;
                $brokerFilter = 'all';
            } elseif ($requestedUserId === 'mine') {
                $resolvedUserId = $userId;
                $brokerFilter = 'mine';
            } else {
                $resolvedUserId = (int)$requestedUserId;
                $brokerFilter = (string)$resolvedUserId;
            }
        }

        $situation = trim((string)($queryParams['situation'] ?? 'today'));
        if (!in_array($situation, ['overdue', 'today', 'future', 'completed', 'all'], true)) {
            $situation = 'today';
        }

        $filters = [
            'situation' => $situation,
            'user_id' => $resolvedUserId,
            'stage' => trim((string)($queryParams['stage'] ?? '')),
            'date_from' => trim((string)($queryParams['date_from'] ?? '')),
            'date_to' => trim((string)($queryParams['date_to'] ?? '')),
            'q' => trim((string)($queryParams['q'] ?? '')),
            'limit' => 150,
        ];

        // Contagens para os cards de resumo da dashboard (respeitando o escopo do usuário se corretor)
        $scopedCountsId = ($userRole === 'corretor' && $brokerFilter === 'mine') ? $userId : null;
        $counts = $this->repository->getDashboardCounts($scopedCountsId);

        // Lista de follow-ups filtrada
        $followUps = $this->repository->getFilteredFollowUps($filters);

        // Lista de corretores para o filtro
        $brokers = $this->leadRepository->getActiveBrokers();

        return [
            'followUps' => $followUps,
            'counts' => $counts,
            'filters' => [
                'situation' => $situation,
                'user_id' => $brokerFilter,
                'stage' => $filters['stage'],
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'q' => $filters['q'],
            ],
            'brokers' => $brokers,
            'currentUser' => $currentUser,
            'userRole' => $userRole,
        ];
    }
}
