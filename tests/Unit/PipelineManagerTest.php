<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Modules\Leads\Pipeline\PipelineManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PipelineManagerTest extends TestCase
{
    public function testValidStagesExist(): void
    {
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_NOVO));
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_CONTATO_REALIZADO));
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_QUALIFICACAO));
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_VISITA_AGENDADA));
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_PROPOSTA));
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_NEGOCIACAO));
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_GANHO));
        $this->assertTrue(PipelineManager::isValidStage(PipelineManager::STAGE_PERDIDO));

        $this->assertFalse(PipelineManager::isValidStage('estagio_inexistente'));
    }

    public function testAllowedForwardTransitions(): void
    {
        // Novo -> Contato realizado, qualificação, perdido
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_NOVO, PipelineManager::STAGE_CONTATO_REALIZADO));
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_NOVO, PipelineManager::STAGE_QUALIFICACAO));
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_NOVO, PipelineManager::STAGE_PERDIDO));

        // Novo -> Ganho direto (não permitido)
        $this->assertFalse(PipelineManager::canTransition(PipelineManager::STAGE_NOVO, PipelineManager::STAGE_GANHO));

        // Proposta -> Ganho ou Perdido
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_PROPOSTA, PipelineManager::STAGE_GANHO));
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_PROPOSTA, PipelineManager::STAGE_PERDIDO));
    }

    public function testReactivationFromLost(): void
    {
        // Perdido -> Novo, Qualificação ou Negociação (reativação)
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_PERDIDO, PipelineManager::STAGE_NOVO));
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_PERDIDO, PipelineManager::STAGE_QUALIFICACAO));
        $this->assertTrue(PipelineManager::canTransition(PipelineManager::STAGE_PERDIDO, PipelineManager::STAGE_NEGOCIACAO));
    }

    public function testTransitionToPerdidoRequiresLossReason(): void
    {
        $this->assertTrue(PipelineManager::requiresLossReason(PipelineManager::STAGE_PERDIDO));
        $this->assertFalse(PipelineManager::requiresLossReason(PipelineManager::STAGE_QUALIFICACAO));

        // Sem motivo de perda
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O motivo da perda é estritamente obrigatório');
        PipelineManager::validateTransition(PipelineManager::STAGE_NOVO, PipelineManager::STAGE_PERDIDO, null);
    }

    public function testTransitionToPerdidoWithShortReasonIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O motivo da perda deve ser detalhado');
        PipelineManager::validateTransition(PipelineManager::STAGE_NOVO, PipelineManager::STAGE_PERDIDO, 'Oi');
    }

    public function testTransitionToPerdidoWithValidReasonSucceeds(): void
    {
        // Não deve lançar exceção
        PipelineManager::validateTransition(
            PipelineManager::STAGE_NOVO,
            PipelineManager::STAGE_PERDIDO,
            'Cliente optou por comprar em outra imobiliária.'
        );
        $this->assertTrue(true);
    }

    public function testDisallowedTransitionThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não é permitida pelo pipeline');
        PipelineManager::validateTransition(PipelineManager::STAGE_NOVO, PipelineManager::STAGE_GANHO, null);
    }

    public function testBadgeClassesReturnExpectedStrings(): void
    {
        $this->assertSame('badge-novo', PipelineManager::getBadgeClass(PipelineManager::STAGE_NOVO));
        $this->assertSame('badge-ganho', PipelineManager::getBadgeClass(PipelineManager::STAGE_GANHO));
        $this->assertSame('badge-perdido', PipelineManager::getBadgeClass(PipelineManager::STAGE_PERDIDO));
    }
}
