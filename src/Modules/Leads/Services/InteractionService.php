<?php

declare(strict_types=1);

namespace App\Modules\Leads\Services;

use App\Audit\AuditService;
use App\Modules\Leads\Repositories\InteractionRepository;
use App\Modules\Leads\Repositories\LeadRepository;
use App\Support\DateHelper;
use InvalidArgumentException;

final class InteractionService
{
    public const ALLOWED_TYPES = [
        'ligacao' => 'Ligação Telefônica',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'visita' => 'Visita Presencial',
        'reuniao' => 'Reunião',
        'anotacao' => 'Anotação Interna',
        'outro' => 'Outro Contato',
    ];

    private InteractionRepository $interactionRepository;
    private LeadRepository $leadRepository;

    public function __construct(
        ?InteractionRepository $interactionRepository = null,
        ?LeadRepository $leadRepository = null
    ) {
        $this->interactionRepository = $interactionRepository ?? new InteractionRepository();
        $this->leadRepository = $leadRepository ?? new LeadRepository();
    }

    public function getRepository(): InteractionRepository
    {
        return $this->interactionRepository;
    }

    /**
     * Registra uma nova interação no histórico do lead com auditoria e proteção contra duplicidade.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, interaction_id?: int, error?: string, errors?: array<string, string>, duplicate?: bool}
     */
    public function createInteraction(int $leadId, array $input, int $actorUserId): array
    {
        $lead = $this->leadRepository->findById($leadId);
        if (!$lead) {
            return ['success' => false, 'error' => 'Lead não encontrado.'];
        }

        // 1. Verificação de Idempotência (evita duplicações por reenvios acidentais ou duplo clique)
        $idempotencyKey = !empty($input['idempotency_key']) ? trim((string)$input['idempotency_key']) : null;
        if ($idempotencyKey !== null) {
            $existing = $this->interactionRepository->findByIdempotencyKey($idempotencyKey);
            if ($existing) {
                return [
                    'success' => true,
                    'interaction_id' => (int)$existing['id'],
                    'duplicate' => true,
                ];
            }
        }

        // 2. Validação dos Campos
        $errors = [];
        $type = trim((string)($input['type'] ?? ''));
        if (!array_key_exists($type, self::ALLOWED_TYPES)) {
            $errors['type'] = 'Selecione um tipo de interação válido.';
        }

        $notes = trim((string)($input['notes'] ?? ''));
        if ($notes === '') {
            $errors['notes'] = 'A observação da interação é obrigatória.';
        } elseif (mb_strlen($notes) < 2) {
            $errors['notes'] = 'A observação deve conter ao menos 2 caracteres.';
        }

        $contactedAtInput = trim((string)($input['contacted_at'] ?? ''));
        $contactedAt = DateHelper::nowString();
        if ($contactedAtInput !== '') {
            try {
                $contactedAt = DateHelper::fromHtmlDatetimeLocal($contactedAtInput);
            } catch (InvalidArgumentException $e) {
                $errors['contacted_at'] = 'Data e hora do contato inválida.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 3. Persistência
        $interactionData = [
            'lead_id' => $leadId,
            'user_id' => $actorUserId,
            'type' => $type,
            'notes' => $notes,
            'contacted_at' => $contactedAt,
            'idempotency_key' => $idempotencyKey,
        ];

        $interactionId = $this->interactionRepository->create($interactionData);

        // 4. Auditoria
        AuditService::log(
            action: 'lead.interaction_created',
            entityType: 'interaction',
            entityId: (string)$interactionId,
            oldValues: null,
            newValues: [
                'lead_id' => $leadId,
                'lead_name' => $lead['name'],
                'type' => $type,
                'type_label' => self::ALLOWED_TYPES[$type],
                'contacted_at' => $contactedAt,
            ],
            userId: $actorUserId
        );

        return [
            'success' => true,
            'interaction_id' => $interactionId,
            'duplicate' => false,
        ];
    }

    /**
     * Retorna a lista cronológica de interações para o lead.
     *
     * @return list<array<string, mixed>>
     */
    public function getChronologicalInteractions(int $leadId): array
    {
        return $this->interactionRepository->getByLeadId($leadId);
    }

    public static function getTypeLabel(string $type): string
    {
        return self::ALLOWED_TYPES[$type] ?? ucfirst($type);
    }

    public static function getTypeBadgeClass(string $type): string
    {
        return match ($type) {
            'ligacao' => 'badge-interaction-call',
            'whatsapp' => 'badge-interaction-whatsapp',
            'email' => 'badge-interaction-email',
            'visita' => 'badge-interaction-visit',
            'reuniao' => 'badge-interaction-meeting',
            'anotacao' => 'badge-interaction-note',
            default => 'badge-neutral',
        };
    }
}
