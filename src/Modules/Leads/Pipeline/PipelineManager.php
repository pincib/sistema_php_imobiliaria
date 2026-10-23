<?php

declare(strict_types=1);

namespace App\Modules\Leads\Pipeline;

use InvalidArgumentException;

final class PipelineManager
{
    public const STAGE_NOVO = 'novo';
    public const STAGE_CONTATO_REALIZADO = 'contato_realizado';
    public const STAGE_QUALIFICACAO = 'qualificacao';
    public const STAGE_VISITA_AGENDADA = 'visita_agendada';
    public const STAGE_PROPOSTA = 'proposta';
    public const STAGE_NEGOCIACAO = 'negociacao';
    public const STAGE_GANHO = 'ganho';
    public const STAGE_PERDIDO = 'perdido';

    /**
     * Dicionário completo de estágios com rótulos amigáveis.
     */
    public const STAGES = [
        self::STAGE_NOVO => 'Novo Lead',
        self::STAGE_CONTATO_REALIZADO => 'Contato Realizado',
        self::STAGE_QUALIFICACAO => 'Qualificação',
        self::STAGE_VISITA_AGENDADA => 'Visita Agendada',
        self::STAGE_PROPOSTA => 'Proposta',
        self::STAGE_NEGOCIACAO => 'Negociação',
        self::STAGE_GANHO => 'Ganho / Fechado',
        self::STAGE_PERDIDO => 'Perdido',
    ];

    /**
     * Matriz de transições permitidas no pipeline.
     * Define com segurança quais estágios podem transitar para quais.
     */
    private const ALLOWED_TRANSITIONS = [
        self::STAGE_NOVO => [
            self::STAGE_CONTATO_REALIZADO,
            self::STAGE_QUALIFICACAO,
            self::STAGE_PERDIDO,
        ],
        self::STAGE_CONTATO_REALIZADO => [
            self::STAGE_QUALIFICACAO,
            self::STAGE_VISITA_AGENDADA,
            self::STAGE_PERDIDO,
        ],
        self::STAGE_QUALIFICACAO => [
            self::STAGE_VISITA_AGENDADA,
            self::STAGE_PROPOSTA,
            self::STAGE_PERDIDO,
        ],
        self::STAGE_VISITA_AGENDADA => [
            self::STAGE_PROPOSTA,
            self::STAGE_NEGOCIACAO,
            self::STAGE_QUALIFICACAO,
            self::STAGE_PERDIDO,
        ],
        self::STAGE_PROPOSTA => [
            self::STAGE_NEGOCIACAO,
            self::STAGE_GANHO,
            self::STAGE_PERDIDO,
        ],
        self::STAGE_NEGOCIACAO => [
            self::STAGE_GANHO,
            self::STAGE_PROPOSTA,
            self::STAGE_PERDIDO,
        ],
        self::STAGE_GANHO => [
            self::STAGE_NEGOCIACAO, // Estorno/revisão pós-fechamento
        ],
        self::STAGE_PERDIDO => [
            self::STAGE_NOVO,          // Reativação
            self::STAGE_QUALIFICACAO,  // Reabertura direta
            self::STAGE_NEGOCIACAO,    // Retomada de contato
        ],
    ];

    /**
     * Sugestões de motivos de perda predefinidos.
     */
    public const LOSS_REASONS = [
        'Preço fora do orçamento',
        'Comprou com outra imobiliária/proprietário',
        'Desistiu de comprar/alugar',
        'Imóveis disponíveis não atenderam aos critérios',
        'Contato inexistente / Sem retorno após tentativas',
        'Localização não atendeu às necessidades',
        'Outro',
    ];

    /**
     * Valida se um estágio é reconhecido pelo sistema.
     */
    public static function isValidStage(string $stage): bool
    {
        return array_key_exists($stage, self::STAGES);
    }

    /**
     * Retorna o nome amigável de um estágio.
     */
    public static function getLabel(string $stage): string
    {
        return self::STAGES[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    /**
     * Verifica se uma transição de estágio é estritamente permitida.
     */
    public static function canTransition(string $fromStage, string $toStage): bool
    {
        if ($fromStage === $toStage) {
            return false;
        }

        if (!self::isValidStage($fromStage) || !self::isValidStage($toStage)) {
            return false;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$fromStage] ?? [];
        return in_array($toStage, $allowed, true);
    }

    /**
     * Retorna lista de estágios para os quais o lead pode transitar a partir do estágio atual.
     *
     * @return list<string>
     */
    public static function getAllowedNextStages(string $currentStage): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStage] ?? [];
    }

    /**
     * Determina se o estágio de destino exige obrigatoriamente um motivo de perda.
     */
    public static function requiresLossReason(string $stage): bool
    {
        return $stage === self::STAGE_PERDIDO;
    }

    /**
     * Valida os requisitos para uma transição de estágio.
     *
     * @throws InvalidArgumentException
     */
    public static function validateTransition(string $fromStage, string $toStage, ?string $lossReason): void
    {
        if (!self::isValidStage($fromStage)) {
            throw new InvalidArgumentException("Estágio de origem inválido: {$fromStage}");
        }

        if (!self::isValidStage($toStage)) {
            throw new InvalidArgumentException("Estágio de destino inválido: {$toStage}");
        }

        if (!self::canTransition($fromStage, $toStage)) {
            $fromLabel = self::getLabel($fromStage);
            $toLabel = self::getLabel($toStage);
            throw new InvalidArgumentException("A transição do estágio '{$fromLabel}' para '{$toLabel}' não é permitida pelo pipeline.");
        }

        if (self::requiresLossReason($toStage)) {
            $reason = trim($lossReason ?? '');
            if ($reason === '') {
                throw new InvalidArgumentException('O motivo da perda é estritamente obrigatório ao mover o lead para o estágio Perdido.');
            }
            if (mb_strlen($reason) < 3) {
                throw new InvalidArgumentException('O motivo da perda deve ser detalhado (mínimo de 3 caracteres).');
            }
        }
    }

    /**
     * Retorna a classe CSS de badge correspondente ao estágio.
     */
    public static function getBadgeClass(string $stage): string
    {
        return match ($stage) {
            self::STAGE_NOVO => 'badge-novo',
            self::STAGE_CONTATO_REALIZADO => 'badge-contato',
            self::STAGE_QUALIFICACAO => 'badge-qualificacao',
            self::STAGE_VISITA_AGENDADA => 'badge-visita',
            self::STAGE_PROPOSTA => 'badge-proposta',
            self::STAGE_NEGOCIACAO => 'badge-negociacao',
            self::STAGE_GANHO => 'badge-ganho',
            self::STAGE_PERDIDO => 'badge-perdido',
            default => 'badge-neutral',
        };
    }
}
