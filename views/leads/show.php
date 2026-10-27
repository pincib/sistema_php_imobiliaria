<?php
/**
 * @var array<string, mixed> $lead
 * @var array<string, string> $stages
 * @var string $currentStage
 * @var list<string> $allowedNextStages
 * @var list<string> $lossReasons
 * @var list<array<string, mixed>> $assignments
 * @var list<array<string, mixed>> $associatedProperties
 * @var list<array<string, mixed>> $availableProperties
 * @var list<array<string, mixed>> $propertySuggestions
 * @var array<string, mixed> $propertySearch
 * @var list<array<string, mixed>> $brokers
 * @var list<array<string, mixed>> $interactions
 * @var array<string, mixed>|null $activeFollowUp
 * @var list<array<string, mixed>> $followUpsHistory
 * @var array<string, string> $sources
 * @var array<string, mixed>|null $currentUser
 * @var callable $e
 * @var callable $formatPhone
 * @var callable $formatDate
 * @var callable $humanDiff
 * @var callable $getFollowUpStatus
 * @var callable $getStatusLabel
 * @var callable $getStatusBadgeClass
 * @var callable $getInteractionLabel
 * @var callable $getInteractionBadgeClass
 * @var array<string, string> $interactionTypes
 * @var callable $getStageBadge
 * @var callable $getStageLabel
 * @var string $csrfField
 */

$stageOrder = [
    'novo',
    'contato_realizado',
    'qualificacao',
    'visita_agendada',
    'proposta',
    'negociacao',
    'ganho',
];

$currentIndex = array_search($currentStage, $stageOrder, true);
$isLost = $currentStage === 'perdido';
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
$cleanPhone = preg_replace('/\D+/', '', $lead['phone'] ?? '') ?? '';
?>
<section class="contact-label-section"><form method="post" action="/contacts/leads/<?= (int)$lead['id'] ?>/invalid"><?= $csrfField ?><input type="hidden" name="invalid_contact" value="<?= $lead['invalid_contact']?'0':'1' ?>"><input type="hidden" name="return_to" value="/leads/<?= (int)$lead['id'] ?>"><?php if($lead['invalid_contact']): ?><strong class="kanban-invalid">Contato inválido</strong> <?php endif ?><button class="outline secondary" type="submit"><?= $lead['invalid_contact']?'Remover rótulo de contato inválido':'Marcar contato inválido' ?></button></form></section>


<!-- Navegação e Cabeçalho do Lead -->
<div class="lead-page-header" style="margin-bottom: 1.5rem;">
    <a href="/leads" style="font-size: 0.85rem; color: var(--pico-muted-color); text-decoration: none;">
        &larr; Voltar para lista de leads
    </a>

    <div class="lead-page-header-main" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem;">
        <div class="lead-page-identity">
            <div class="lead-identity-line" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="margin: 0; font-size: clamp(1.35rem, 4vw, 1.85rem); letter-spacing: -0.02em;"><?= $e($lead['name']) ?></h1>
                <span class="badge <?= $e($getStageBadge($lead['stage'])) ?>" style="font-size: 0.8rem;">
                    <?= $e($getStageLabel($lead['stage'])) ?>
                </span>
                <span class="badge badge-neutral" style="font-size: 0.75rem;">
                    Canal: <?= $e($sources[$lead['source']] ?? ucfirst($lead['source'])) ?>
                </span>
            </div>
            <p class="lead-page-meta" style="color: var(--pico-muted-color); margin: 0.35rem 0 0 0; font-size: 0.875rem;">
                Lead #<?= $e((string)$lead['id']) ?> &bull; Cadastrado em <?= $e($formatDate($lead['created_at'])) ?>
                &bull; Responsável: <strong><?= $e($lead['assigned_user_name'] ?? 'Não atribuído') ?></strong>
            </p>
        </div>

        <!-- Botões de Ação Rápida com Micro-SVGs e Tokens de Cor -->
        <div class="lead-header-actions">
            <?php if (!empty($cleanPhone)): ?>
                <a href="tel:<?= $e($cleanPhone) ?>" role="button" class="outline" title="Ligar para o lead" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    Ligar
                </a>
                <a href="/leads/<?= (int)$lead['id'] ?>/whatsapp" role="button" class="btn-whatsapp" title="Preparar mensagem e abrir conversa no WhatsApp">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    WhatsApp
                </a>
            <?php endif; ?>
            <?php if (empty($cleanPhone)): ?><a href="/leads/<?= (int)$lead['id'] ?>/whatsapp" role="button" class="btn-whatsapp">WhatsApp</a><?php endif; ?>

            <?php if (!empty($lead['email'])): ?>
                <a href="mailto:<?= $e($lead['email']) ?>" role="button" class="outline" title="Enviar e-mail" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    E-mail
                </a>
            <?php endif; ?>

            <a href="/leads/<?= $e((string)$lead['id']) ?>/edit" role="button" class="outline secondary" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
            </a>

            <?php if ($isAdmin): ?>
                <form action="/leads/<?= $e((string)$lead['id']) ?>/delete" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('ATENÇÃO: Deseja realmente excluir este lead permanentemente? Esta ação é irreversível e ficará registrada na auditoria.');">
                    <?= $csrfField ?>
                    <button type="submit" class="btn-danger" title="Excluir Lead">
                        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Excluir
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =======================================================================
     SEÇÃO 1: PIPELINE DE VENDAS E MÁQUINA DE ESTADOS
     ======================================================================= -->
<article class="pipeline-stepper-container" style="margin-bottom: 1.5rem;">
    <header style="margin-bottom: 1.25rem;">
        <h2 style="margin: 0; font-size: 1.15rem;">Pipeline de Vendas</h2>
        <p style="margin: 0; color: var(--pico-muted-color); font-size: 0.85rem;">
            Acompanhe o progresso da negociação e registre as transições autorizadas de estágio.
        </p>
    </header>

    <!-- Stepper Visual -->
    <ul class="pipeline-stepper" aria-label="Progresso no Pipeline de Vendas">
        <?php foreach ($stageOrder as $idx => $stageKey): ?>
            <?php
                $stepNumber = $idx + 1;
                $isStepCurrent = $currentStage === $stageKey;
                $isStepCompleted = ($currentIndex !== false && $idx < $currentIndex && !$isLost);
                $stepClass = '';
                if ($isStepCurrent) {
                    $stepClass = 'is-current';
                } elseif ($isStepCompleted) {
                    $stepClass = 'is-completed';
                }
            ?>
            <li class="pipeline-step <?= $stepClass ?>">
                <span class="step-number"><?php if ($isStepCompleted): ?><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg><?php else: ?><?= $stepNumber ?><?php endif; ?></span>
                <span class="step-title"><?= $e($stages[$stageKey]) ?></span>
            </li>
        <?php endforeach; ?>

        <!-- Estágio Perdido -->
        <li class="pipeline-step <?= $isLost ? 'is-lost' : '' ?>">
            <span class="step-number"><?php if ($isLost): ?><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg><?php else: ?>!<?php endif; ?></span>
            <span class="step-title">Perdido</span>
        </li>
    </ul>

    <!-- Aviso especial se o lead estiver como Perdido -->
    <?php if ($isLost): ?>
        <div class="alert alert-info" style="margin-top: 1.25rem; margin-bottom: 0;">
            <div>
                <strong>Lead marcado como Perdido</strong>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem;">
                    <strong>Motivo registrado:</strong> <?= $e($lead['loss_reason'] ?? 'Não informado.') ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Formulário de Transição de Estágio -->
    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--pico-muted-border-color);">
        <h3 style="margin-bottom: 0.75rem; font-size: 1rem;">Mudar Estágio do Lead</h3>

        <?php if (empty($allowedNextStages)): ?>
            <p style="color: var(--pico-muted-color); font-size: 0.9rem; margin: 0;">
                Este lead está em um estágio final e não possui transições automáticas disponíveis.
            </p>
        <?php else: ?>
            <form action="/leads/<?= $e((string)$lead['id']) ?>/stage" method="POST" id="stage-transition-form" style="margin: 0;">
                <?= $csrfField ?>

                <div class="stage-transition-grid">
                    <div>
                        <label for="stage-select" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.25rem;">
                            Selecione o próximo estágio autorizado:
                        </label>
                        <select name="stage" id="stage-select" required onchange="handleStageChange(this.value)">
                            <option value="">-- Selecionar próximo estágio --</option>
                            <?php foreach ($allowedNextStages as $nextKey): ?>
                                <option value="<?= $e($nextKey) ?>">
                                    <?= $e($stages[$nextKey] ?? $nextKey) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="padding-top: 1.55rem;">
                        <button type="submit" class="primary" style="margin: 0; white-space: nowrap;">
                            Confirmar Transição
                        </button>
                    </div>
                </div>

                <!-- Campo Condicional Obrigatório: Motivo da Perda -->
                <div id="loss-reason-container" class="loss-reason-card" style="display: none;">
                    <label for="loss_reason" class="loss-reason-title">
                        Motivo da Perda (Obrigatório) *
                    </label>
                    <p style="font-size: 0.8rem; color: var(--pico-muted-color); margin-top: 0; margin-bottom: 0.5rem;">
                        Por favor, detalhe o motivo para mantermos a auditoria de perdas consistente:
                    </p>

                    <!-- Sugestões rápidas de motivos -->
                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                        <?php foreach ($lossReasons as $suggestion): ?>
                            <button type="button" 
                                    class="outline secondary" 
                                    style="padding: 0.2rem 0.5rem; font-size: 0.75rem; margin: 0;"
                                    onclick="document.getElementById('loss_reason').value = '<?= $e($suggestion) ?>';">
                                <?= $e($suggestion) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <textarea name="loss_reason" 
                              id="loss_reason" 
                              rows="2" 
                              placeholder="Descreva o motivo detalhado pelo qual a oportunidade não avançou..."></textarea>
                </div>
            </form>

            <script>
                function handleStageChange(val) {
                    const lossContainer = document.getElementById('loss-reason-container');
                    const lossInput = document.getElementById('loss_reason');
                    if (val === 'perdido') {
                        lossContainer.style.display = 'block';
                        lossInput.required = true;
                        lossInput.focus();
                    } else {
                        lossContainer.style.display = 'none';
                        lossInput.required = false;
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</article>

<!-- =======================================================================
     SEÇÃO 2: CONTROLE DE FOLLOW-UP E RETORNO AGENDADO
     ======================================================================= -->
<article id="follow-up-section" class="lead-panel" style="margin-bottom: 1.5rem;">
    <header class="lead-panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--pico-muted-border-color); padding-bottom: 0.5rem;">
        <div>
            <h2 style="margin: 0; font-size: 1.15rem;">Controle de Follow-up (Retorno Programado)</h2>
            <p style="margin: 0; color: var(--pico-muted-color); font-size: 0.85rem;">
                Gerencie o próximo contato para garantir que este lead não fique esquecido.
            </p>
        </div>
        <div>
            <button type="button" 
                    class="outline" 
                    style="font-size: 0.8rem; padding: 0.35rem 0.75rem; margin: 0;"
                    onclick="document.getElementById('modal-lead-new-followup').showModal()">
                + Agendar Novo Follow-up
            </button>
        </div>
    </header>

    <?php if ($activeFollowUp): ?>
        <?php 
            $followUpStatusKey = $getFollowUpStatus($activeFollowUp['scheduled_at'], $activeFollowUp['status']);
        ?>
        <div class="follow-up-card is-<?= $e($followUpStatusKey) ?>" style="margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem; flex-wrap: wrap;">
                        <span class="badge <?= $e($getStatusBadgeClass($followUpStatusKey)) ?>">
                            <?= $e($getStatusLabel($followUpStatusKey)) ?>
                        </span>
                        <strong class="follow-up-date<?= $followUpStatusKey === 'overdue' ? ' is-overdue' : '' ?>" style="font-size: 1.1rem;">
                            <?= $e($formatDate($activeFollowUp['scheduled_at'])) ?>
                        </strong>
                        <span style="font-size: 0.85rem; color: var(--pico-muted-color);">
                            (<?= $e($humanDiff($activeFollowUp['scheduled_at'], $activeFollowUp['status'])) ?>)
                        </span>
                    </div>

                    <p style="margin: 0.25rem 0; font-size: 0.9rem;">
                        <strong>Responsável pelo retorno:</strong> <?= $e($activeFollowUp['broker_name']) ?>
                    </p>

                    <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; color: var(--pico-color);">
                        <strong>Objetivo:</strong> <?= $e($activeFollowUp['notes'] ?? 'Sem observações adicionais.') ?>
                    </p>
                </div>

                <div class="lead-follow-up-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="button" 
                            class="primary" 
                            style="font-size: 0.85rem; padding: 0.45rem 0.9rem; margin: 0;"
                            onclick="document.getElementById('modal-lead-complete').showModal()">
                        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                        Concluir / Registrar Resultado
                    </button>

                    <button type="button" 
                            class="outline secondary" 
                            style="font-size: 0.85rem; padding: 0.45rem 0.9rem; margin: 0;"
                            onclick="document.getElementById('modal-lead-reschedule').showModal()">
                        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        Reagendar
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: CONCLUIR FOLLOW-UP NO LEAD -->
        <dialog id="modal-lead-complete" aria-labelledby="title-lead-complete" aria-modal="true">
            <article style="max-width: 550px; text-align: left;">
                <header style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 id="title-lead-complete" style="margin: 0; font-size: 1.25rem;">Registrar Resultado do Contato</h2>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--pico-muted-color);">
                            Lead: <strong><?= $e($lead['name']) ?></strong> &bull; Programado para: <?= $e($formatDate($activeFollowUp['scheduled_at'])) ?>
                        </p>
                    </div>
                    <button type="button" aria-label="Fechar janela" class="close" onclick="document.getElementById('modal-lead-complete').close();">&times;</button>
                </header>

                <form action="/leads/<?= $e((string)$lead['id']) ?>/follow-ups/<?= $e((string)$activeFollowUp['id']) ?>/complete" 
                      method="POST" 
                      onsubmit="if (!this.checkValidity()) return true; if (this.dataset.submitted) return false; this.dataset.submitted='true'; const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled=true; btn.setAttribute('aria-busy', 'true'); }">
                    <?= $csrfField ?>
                    <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">

                    <div class="grid" style="margin-bottom: 0.75rem;">
                        <div>
                            <label for="lead-complete-type" style="font-size: 0.85rem; font-weight: 600;">
                                Canal de Contato <span aria-hidden="true">*</span>
                            </label>
                            <select name="type" id="lead-complete-type" required aria-required="true">
                                <option value="ligacao">📞 Ligação Telefônica</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="visita">🏠 Visita Presencial</option>
                                <option value="reuniao">👥 Reunião Presencial/Online</option>
                                <option value="email">✉️ E-mail</option>
                                <option value="outro">Outro Canal</option>
                            </select>
                        </div>

                        <div>
                            <label for="lead-complete-contacted-at" style="font-size: 0.85rem; font-weight: 600;">
                                Data e Hora Realizada <span aria-hidden="true">*</span>
                            </label>
                            <input type="datetime-local" 
                                   name="contacted_at" 
                                   id="lead-complete-contacted-at" 
                                   value="<?= date('Y-m-d\TH:i') ?>" 
                                   max="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>"
                                   required
                                   aria-required="true">
                        </div>
                    </div>

                    <label for="lead-complete-result-notes" style="font-size: 0.85rem; font-weight: 600;">
                        Resultado do Contato (Obrigatório) <span aria-hidden="true">*</span>
                        <textarea name="result_notes" 
                                  id="lead-complete-result-notes" 
                                  rows="3" 
                                  placeholder="Descreva detalhadamente o retorno do cliente, objeções levantadas, interesses ou próximos passos acordados..." 
                                  required 
                                  minlength="2"
                                  maxlength="2000"
                                  aria-required="true"></textarea>
                    </label>

                    <!-- Opção de Próximo Retorno -->
                    <details style="margin: 1rem 0; border: 1px solid var(--pico-muted-border-color); padding: 0.75rem; border-radius: var(--pico-border-radius);">
                        <summary style="font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            + Agendar Próximo Retorno
                        </summary>

                        <div style="margin-top: 0.75rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; margin-bottom: 0.75rem;">
                                <input type="checkbox" name="schedule_next" value="1" onchange="document.getElementById('lead-next-schedule-fields').style.display = this.checked ? 'block' : 'none';">
                                <span>Sim, agendar uma nova data de retorno</span>
                            </label>

                            <div id="lead-next-schedule-fields" style="display: none;">
                                <div class="grid" style="margin-bottom: 0.5rem;">
                                    <div>
                                        <label for="lead-next-scheduled-at" style="font-size: 0.8rem; font-weight: 600;">
                                            Nova Data / Hora
                                        </label>
                                        <input type="datetime-local" 
                                               name="next_scheduled_at" 
                                               id="lead-next-scheduled-at"
                                               min="<?= date('Y-m-d\TH:i') ?>">
                                    </div>
                                    <div>
                                        <label for="lead-next-user-id" style="font-size: 0.8rem; font-weight: 600;">
                                            Corretor Responsável
                                        </label>
                                        <select name="next_user_id" id="lead-next-user-id">
                                            <?php foreach ($brokers as $b): ?>
                                                <option value="<?= $e((string)$b['id']) ?>" <?= (int)$b['id'] === (int)$activeFollowUp['user_id'] ? 'selected' : '' ?>>
                                                    <?= $e($b['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <label for="lead-next-notes" style="font-size: 0.8rem; font-weight: 600;">
                                    Objetivo do Próximo Retorno
                                    <input type="text" 
                                           name="next_notes" 
                                           id="lead-next-notes" 
                                           placeholder="Ex: Enviar proposta do imóvel AP101 após visita"
                                           maxlength="255">
                                </label>
                            </div>
                        </div>
                    </details>

                    <footer style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                        <button type="button" class="outline secondary" onclick="document.getElementById('modal-lead-complete').close();" style="width: auto; margin: 0;">
                            Cancelar
                        </button>
                        <button type="submit" class="primary" style="width: auto; margin: 0;">
                            Salvar Resultado & Concluir
                        </button>
                    </footer>
                </form>
            </article>
        </dialog>

        <!-- MODAL: REAGENDAR FOLLOW-UP NO LEAD -->
        <dialog id="modal-lead-reschedule" aria-labelledby="title-lead-reschedule" aria-modal="true">
            <article style="max-width: 500px; text-align: left;">
                <header style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 id="title-lead-reschedule" style="margin: 0; font-size: 1.25rem;">Reagendar Retorno</h2>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--pico-muted-color);">
                            Lead: <strong><?= $e($lead['name']) ?></strong>
                        </p>
                    </div>
                    <button type="button" aria-label="Fechar janela" class="close" onclick="document.getElementById('modal-lead-reschedule').close();">&times;</button>
                </header>

                <form action="/leads/<?= $e((string)$lead['id']) ?>/follow-ups/<?= $e((string)$activeFollowUp['id']) ?>/reschedule" 
                      method="POST" 
                      onsubmit="if (!this.checkValidity()) return true; if (this.dataset.submitted) return false; this.dataset.submitted='true'; const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled=true; btn.setAttribute('aria-busy', 'true'); }">
                    <?= $csrfField ?>
                    <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">

                    <div class="grid" style="margin-bottom: 0.75rem;">
                        <div>
                            <label for="lead-reschedule-date" style="font-size: 0.85rem; font-weight: 600;">
                                Nova Data e Horário <span aria-hidden="true">*</span>
                            </label>
                            <input type="datetime-local" 
                                   name="scheduled_at" 
                                   id="lead-reschedule-date" 
                                   min="<?= date('Y-m-d\TH:i') ?>"
                                   required
                                   aria-required="true">
                        </div>

                        <div>
                            <label for="lead-reschedule-user" style="font-size: 0.85rem; font-weight: 600;">
                                Corretor Responsável
                            </label>
                            <select name="user_id" id="lead-reschedule-user">
                                <?php foreach ($brokers as $b): ?>
                                    <option value="<?= $e((string)$b['id']) ?>" <?= (int)$b['id'] === (int)$activeFollowUp['user_id'] ? 'selected' : '' ?>>
                                        <?= $e($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label for="lead-reschedule-reason" style="font-size: 0.85rem; font-weight: 600;">
                        Motivo do Reagendamento <span aria-hidden="true">*</span>
                        <input type="text" 
                               name="reason" 
                               id="lead-reschedule-reason" 
                               placeholder="Ex: Cliente em viagem até sexta-feira / Pediu para ligar às 15h" 
                               required 
                               minlength="3"
                               maxlength="255"
                               aria-required="true">
                    </label>

                    <footer style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                        <button type="button" class="outline secondary" onclick="document.getElementById('modal-lead-reschedule').close();" style="width: auto; margin: 0;">
                            Cancelar
                        </button>
                        <button type="submit" class="primary" style="width: auto; margin: 0;">
                            Confirmar Reagendamento
                        </button>
                    </footer>
                </form>
            </article>
        </dialog>
    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 1.25rem; background-color: var(--pico-card-background-color); border: 1px dashed var(--pico-muted-border-color); border-radius: var(--pico-border-radius);">
            <div>
                <strong style="font-size: 0.95rem; display: block;">Nenhum retorno agendado no momento</strong>
                <span style="font-size: 0.85rem; color: var(--pico-muted-color);">
                    Defina uma data e horário para garantir o acompanhamento contínuo e reduzir esquecimentos.
                </span>
            </div>
            <div>
                <button type="button" 
                        class="primary" 
                        style="font-size: 0.85rem; padding: 0.45rem 0.9rem; margin: 0;"
                        onclick="document.getElementById('modal-lead-new-followup').showModal()">
                    + Agendar Follow-up
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL: AGENDAR NOVO FOLLOW-UP -->
    <dialog id="modal-lead-new-followup" aria-labelledby="title-lead-new-followup" aria-modal="true">
        <article style="max-width: 500px; text-align: left;">
            <header style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h2 id="title-lead-new-followup" style="margin: 0; font-size: 1.25rem;">Agendar Follow-up</h2>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--pico-muted-color);">
                        Lead: <strong><?= $e($lead['name']) ?></strong>
                    </p>
                </div>
                <button type="button" aria-label="Fechar janela" class="close" onclick="document.getElementById('modal-lead-new-followup').close();">&times;</button>
            </header>

            <form action="/leads/<?= $e((string)$lead['id']) ?>/follow-ups" 
                  method="POST" 
                  onsubmit="if (!this.checkValidity()) return true; if (this.dataset.submitted) return false; this.dataset.submitted='true'; const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled=true; btn.setAttribute('aria-busy', 'true'); }">
                <?= $csrfField ?>
                <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">

                <div class="grid" style="margin-bottom: 0.75rem;">
                    <div>
                        <label for="new-followup-scheduled-at" style="font-size: 0.85rem; font-weight: 600;">
                            Data e Horário Previsto <span aria-hidden="true">*</span>
                        </label>
                        <input type="datetime-local" 
                               name="scheduled_at" 
                               id="new-followup-scheduled-at" 
                               value="<?= date('Y-m-d\TH:i', strtotime('+1 day 10:00')) ?>" 
                               min="<?= date('Y-m-d\TH:i') ?>"
                               required
                               aria-required="true">
                    </div>

                    <div>
                        <label for="new-followup-user-id" style="font-size: 0.85rem; font-weight: 600;">
                            Responsável pelo Retorno <span aria-hidden="true">*</span>
                        </label>
                        <select name="user_id" id="new-followup-user-id" required aria-required="true">
                            <?php foreach ($brokers as $b): ?>
                                <option value="<?= $e((string)$b['id']) ?>" <?= (int)$b['id'] === (int)($lead['user_id'] ?? $currentUser['id']) ? 'selected' : '' ?>>
                                    <?= $e($b['name']) ?> (<?= $e($b['role_label']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label for="new-followup-notes" style="font-size: 0.85rem; font-weight: 600;">
                    Objetivo do Retorno
                    <textarea name="notes" 
                              id="new-followup-notes" 
                              rows="2" 
                              placeholder="Ex: Verificar se cliente conseguiu aprovação de crédito / Apresentar novas opções"
                              maxlength="500"></textarea>
                </label>

                <footer style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                    <button type="button" class="outline secondary" onclick="document.getElementById('modal-lead-new-followup').close();" style="width: auto; margin: 0;">
                        Cancelar
                    </button>
                    <button type="submit" class="primary" style="width: auto; margin: 0;">
                        Confirmar Agendamento
                    </button>
                </footer>
            </form>
        </article>
    </dialog>
</article>

<div class="lead-details-grid">
    <!-- ===================================================================
         SEÇÃO 3: DADOS DE CONTATO E OBSERVAÇÕES
         =================================================================== -->
    <article class="lead-panel" style="height: 100%;">
        <header style="margin-bottom: 1rem; border-bottom: 1px solid var(--pico-muted-border-color); padding-bottom: 0.5rem;">
            <h2 style="margin: 0; font-size: 1.15rem;">Dados Cadastrais</h2>
        </header>

        <dl style="margin: 0;">
            <dt style="font-weight: 600; font-size: 0.85rem; color: var(--pico-muted-color);">Telefone / WhatsApp</dt>
            <dd style="margin-bottom: 1rem; font-size: 1rem;">
                <?= $e($formatPhone($lead['phone'])) ?>
            </dd>

            <dt style="font-weight: 600; font-size: 0.85rem; color: var(--pico-muted-color);">E-mail</dt>
            <dd style="margin-bottom: 1rem; font-size: 1rem;">
                <?= $e($lead['email'] ?? '—') ?>
            </dd>

            <dt style="font-weight: 600; font-size: 0.85rem; color: var(--pico-muted-color);">Canal de Origem</dt>
            <dd style="margin-bottom: 1rem; font-size: 1rem;">
                <?= $e($sources[$lead['source']] ?? ucfirst($lead['source'])) ?>
            </dd>

            <dt style="font-weight: 600; font-size: 0.85rem; color: var(--pico-muted-color);">Observações / Perfil de Interesse</dt>
            <dd style="margin-bottom: 0; font-size: 0.95rem; white-space: pre-wrap; background-color: var(--pico-card-background-color); padding: 0.75rem; border-radius: var(--pico-border-radius); border: 1px solid var(--pico-muted-border-color);">
                <?= $e($lead['notes'] ?? 'Nenhuma observação informada no cadastro.') ?>
            </dd>
        </dl>
    </article>

    <!-- ===================================================================
         SEÇÃO 4: RESPONSÁVEL E TRANSFERÊNCIA DE CARTEIRA AUDITÁVEL
         =================================================================== -->
    <article class="lead-panel" style="height: 100%;">
        <header style="margin-bottom: 1rem; border-bottom: 1px solid var(--pico-muted-border-color); padding-bottom: 0.5rem;">
            <h2 style="margin: 0; font-size: 1.15rem;">Atribuição de Responsável</h2>
        </header>

        <div style="background-color: var(--pico-card-background-color); padding: 0.85rem; border-radius: var(--pico-border-radius); border: 1px solid var(--pico-muted-border-color); margin-bottom: 1.25rem;">
            <span style="font-size: 0.8rem; color: var(--pico-muted-color); display: block;">Corretor Responsável Atual:</span>
            <strong style="font-size: 1.05rem;"><?= $e($lead['assigned_user_name'] ?? 'Não atribuído') ?></strong>
            <?php if (!empty($lead['assigned_user_email'])): ?>
                <span style="font-size: 0.8rem; color: var(--pico-muted-color); display: block;">
                    <?= $e($lead['assigned_user_email']) ?> &bull; <?= $e($lead['assigned_user_role_label'] ?? '') ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Formulário de Troca de Responsável -->
        <details>
            <summary role="button" class="outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; margin-bottom: 1rem;">
                Transferir Responsável
            </summary>

            <form action="/leads/<?= $e((string)$lead['id']) ?>/reassign" method="POST" style="margin-top: 0.75rem;">
                <?= $csrfField ?>

                <label for="to_user_id" style="font-size: 0.85rem;">
                    Novo Responsável *
                    <select name="to_user_id" id="to_user_id" required>
                        <option value="">-- Selecione o novo corretor --</option>
                        <?php foreach ($brokers as $broker): ?>
                            <?php if ((int)$broker['id'] !== (int)($lead['user_id'] ?? 0)): ?>
                                <option value="<?= $e((string)$broker['id']) ?>">
                                    <?= $e($broker['name']) ?> (<?= $e($broker['role_label']) ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="reassign_reason" style="font-size: 0.85rem;">
                    Motivo da Transferência (Auditável) *
                    <input type="text" 
                           name="reason" 
                           id="reassign_reason" 
                           placeholder="Ex: Redistribuição de carteira / Corretor especialista no bairro" 
                           required 
                           minlength="3">
                </label>

                <button type="submit" class="primary" style="font-size: 0.85rem; padding: 0.45rem 1rem;">
                    Confirmar Troca de Responsável
                </button>
            </form>
        </details>

        <!-- Histórico de Atribuições -->
        <h3 style="margin-top: 1.25rem; margin-bottom: 0.5rem; font-size: 0.95rem;">Histórico de Transferências</h3>
        <?php if (empty($assignments)): ?>
            <p style="font-size: 0.85rem; color: var(--pico-muted-color); margin: 0;">
                Nenhuma transferência de responsável registrada para este lead até o momento.
            </p>
        <?php else: ?>
            <ul class="timeline-list">
                <?php foreach ($assignments as $item): ?>
                    <li class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-time">
                            <?= $e($formatDate($item['assigned_at'])) ?>
                        </div>
                        <p class="timeline-text">
                            De: <strong><?= $e($item['from_user_name'] ?? 'Não atribuído') ?></strong> &rarr; 
                            Para: <strong><?= $e($item['to_user_name']) ?></strong>
                        </p>
                        <p style="margin: 0.2rem 0 0 0; font-size: 0.8rem; color: var(--pico-muted-color);">
                            <em>"<?= $e($item['reason']) ?>"</em>
                            &bull; Por: <?= $e($item['assigned_by_name'] ?? 'Sistema') ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</div>

<!-- =======================================================================
     SEÇÃO 5: HISTÓRICO CRONOLÓGICO DE INTERAÇÕES E REGISTRO DE CONTATO
     ======================================================================= -->
<article id="interactions-section" class="lead-panel" style="margin-top: 1.5rem;">
    <header class="lead-panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--pico-muted-border-color); padding-bottom: 0.5rem;">
        <div>
            <h2 style="margin: 0; font-size: 1.15rem;">Histórico de Interações com o Lead</h2>
            <p style="margin: 0; color: var(--pico-muted-color); font-size: 0.85rem;">
                Registro cronológico e auditável de ligações, conversas no WhatsApp, visitas, reuniões e anotações.
            </p>
        </div>
        <div>
            <button type="button" 
                    class="outline" 
                    style="font-size: 0.8rem; padding: 0.35rem 0.75rem; margin: 0;"
                    onclick="document.getElementById('new-interaction-form-details').open = true; document.getElementById('interaction-notes-input').focus();">
                + Nova Interação
            </button>
        </div>
    </header>

    <!-- Formulário Rápido de Registro de Interação -->
    <details id="new-interaction-form-details" style="margin-bottom: 1.5rem; background: var(--pico-card-background-color); border: 1px solid var(--pico-muted-border-color); border-radius: var(--pico-border-radius); padding: 1rem;">
        <summary style="font-size: 0.9rem; font-weight: 600; cursor: pointer;">
            + Registrar Novo Contato ou Nota
        </summary>

        <form action="/leads/<?= $e((string)$lead['id']) ?>/interactions" 
              method="POST" 
              style="margin-top: 1rem; margin-bottom: 0;"
              onsubmit="if (!this.checkValidity()) return true; if (this.dataset.submitted) return false; this.dataset.submitted='true'; const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled=true; btn.setAttribute('aria-busy', 'true'); }">
            <?= $csrfField ?>
            <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">

            <div class="grid" style="margin-bottom: 0.75rem;">
                <div>
                    <label for="interaction-type" style="font-size: 0.85rem; font-weight: 600;">
                        Tipo de Contato <span aria-hidden="true">*</span>
                    </label>
                    <select name="type" id="interaction-type" required aria-required="true">
                        <option value="ligacao">📞 Ligação Telefônica</option>
                        <option value="whatsapp">💬 WhatsApp</option>
                        <option value="visita">🏠 Visita Presencial</option>
                        <option value="reuniao">👥 Reunião Presencial/Online</option>
                        <option value="email">✉️ E-mail</option>
                        <option value="anotacao">📝 Anotação Interna</option>
                        <option value="outro">Outro Contato</option>
                    </select>
                </div>

                <div>
                    <label for="interaction-contacted-at" style="font-size: 0.85rem; font-weight: 600;">
                        Data e Hora do Contato <span aria-hidden="true">*</span>
                    </label>
                    <input type="datetime-local" 
                           name="contacted_at" 
                           id="interaction-contacted-at" 
                           value="<?= date('Y-m-d\TH:i') ?>" 
                           max="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>"
                           required
                           aria-required="true">
                </div>
            </div>

            <label for="interaction-notes-input" style="font-size: 0.85rem; font-weight: 600;">
                Observações do Contato (Obrigatório) <span aria-hidden="true">*</span>
                <textarea name="notes" 
                          id="interaction-notes-input" 
                          rows="3" 
                          placeholder="Detalhes do que foi falado, retorno obtido, interesse em imóveis..." 
                          required 
                          minlength="2"
                          maxlength="2000"
                          aria-required="true"></textarea>
            </label>

            <button type="submit" class="primary" style="font-size: 0.85rem; padding: 0.45rem 1rem; margin-top: 0.5rem;">
                Salvar Interação no Histórico
            </button>
        </form>
    </details>

    <!-- Linha do Tempo Cronológica -->
    <?php if (empty($interactions)): ?>
        <div class="empty-state-box" style="margin: 1rem 0;">
            <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <h3 style="margin-bottom: 0.5rem;">Nenhuma interação registrada ainda</h3>
            <p style="color: var(--pico-muted-color); font-size: 0.85rem; margin: 0;">
                Registre a primeira ligação, conversa de WhatsApp ou visita para iniciar o histórico de relacionamento deste lead.
            </p>
        </div>
    <?php else: ?>
        <ul class="timeline-list" style="margin-top: 1rem;">
            <?php foreach ($interactions as $inter): ?>
                <li class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span class="badge <?= $e($getInteractionBadgeClass($inter['type'])) ?>" style="font-size: 0.75rem;">
                                <?= $e($getInteractionLabel($inter['type'])) ?>
                            </span>
                            <strong style="margin-left: 0.5rem; font-size: 0.9rem;">
                                <?= $e($inter['author_name']) ?>
                            </strong>
                            <span style="font-size: 0.8rem; color: var(--pico-muted-color);">
                                (<?= $e($inter['author_role_label']) ?>)
                            </span>
                        </div>
                        <div class="timeline-time">
                            <?= $e($formatDate($inter['contacted_at'])) ?>
                        </div>
                    </div>
                    <p class="timeline-text" style="margin-top: 0.4rem; white-space: pre-wrap; font-size: 0.9rem; line-height: 1.45; word-break: break-word; overflow-wrap: break-word;">
                        <?= $e($inter['notes']) ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>

<!-- Imóveis vinculados e sugestões automáticas são superfícies distintas: só o vínculo representa confirmação do corretor. -->
<article id="lead-properties" class="lead-panel" style="margin-top: 1.5rem;">
    <section id="linked-properties" aria-labelledby="linked-properties-title">
        <header style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--pico-muted-border-color); padding-bottom: 0.5rem;">
            <div>
                <h2 id="linked-properties-title" style="margin: 0; font-size: 1.15rem;">Imóveis vinculados</h2>
                <p style="margin: 0; color: var(--pico-muted-color); font-size: 0.85rem;">Relações adicionadas ou confirmadas pelo corretor, com tipo e histórico preservados.</p>
            </div>
        </header>
        <?php if (empty($associatedProperties)): ?>
            <p style="color: var(--pico-muted-color);">Nenhum imóvel vinculado a este lead.</p>
        <?php else: ?>
            <div class="lead-properties-table-scroll" role="region" aria-label="Tabela de imóveis vinculados" aria-describedby="linked-properties-scroll-hint" tabindex="0" style="overflow-x: auto; margin-bottom: 1.25rem;">
                <p class="properties-scroll-hint lead-table-scroll-hint" id="linked-properties-scroll-hint">Em telas estreitas, os dados de cada imóvel aparecem agrupados em um cartão.</p>
                <table class="table-compact lead-properties-linked-table property-records-table" style="margin-bottom: 0;">
                    <thead><tr><th scope="col">Código</th><th scope="col">Imóvel</th><th scope="col">Bairro / Cidade</th><th scope="col">Preço</th><th scope="col">Tipo de relação</th><th scope="col">Vinculado em</th><th scope="col">Observações</th><th scope="col">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($associatedProperties as $prop): ?>
                        <tr>
                            <td data-label="Código"><strong><?= $e($prop['code']) ?></strong></td>
                            <td data-label="Imóvel"><?= $e($prop['title']) ?><small style="display:block;color:var(--pico-muted-color);"><?= $e(ucfirst($prop['type'])) ?></small></td>
                            <td data-label="Bairro / Cidade"><?= $e($prop['neighborhood'] ?? '—') ?> / <?= $e($prop['city'] ?? '—') ?></td>
                            <td data-label="Preço"><?= !empty($prop['price']) ? 'R$ ' . number_format((float)$prop['price'], 2, ',', '.') : 'Consulte' ?></td>
                            <td data-label="Tipo de relação"><span class="badge badge-neutral"><?= $e(ucfirst(str_replace('_', ' ', $prop['interest_type']))) ?></span></td>
                            <td data-label="Vinculado em"><?= $e($formatDate($prop['associated_at'])) ?></td>
                            <td data-label="Observações"><?= $e($prop['association_notes'] ?? '—') ?></td>
                            <td data-label="Ações"><form action="/leads/<?= $e((string)$lead['id']) ?>/properties/<?= $e((string)$prop['property_id']) ?>/delete" method="POST" style="margin:0;" onsubmit="return confirm('Deseja desvincular este imóvel do lead?');"><?= $csrfField ?><button type="submit" class="btn-danger lead-property-action" style="padding:.25rem .55rem;font-size:.75rem;margin:0;">Desvincular</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <details>
            <summary role="button" class="outline" style="font-size:.85rem;padding:.4rem .8rem;">+ Vincular imóvel manualmente</summary>
            <form action="/leads/<?= $e((string)$lead['id']) ?>/properties" method="POST" style="margin-top:1rem;">
                <?= $csrfField ?>
                <div class="grid lead-property-form-grid">
                    <label for="property_id">Imóvel do catálogo<select name="property_id" id="property_id" required><option value="">Selecione o imóvel</option><?php foreach ($availableProperties as $avail): ?><option value="<?= $e((string)$avail['id']) ?>">[<?= $e($avail['code']) ?>] <?= $e($avail['title']) ?> — <?= $e($avail['neighborhood'] ?? '') ?> / <?= $e($avail['city'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label for="interest_type">Tipo de relação<select name="interest_type" id="interest_type"><option value="interesse">Interesse geral</option><option value="visita_agendada">Visita agendada</option><option value="visita_realizada">Visita realizada</option><option value="proposta_em_analise">Proposta em análise</option><option value="descartado">Descartado pelo cliente</option></select></label>
                </div>
                <label for="property_notes">Observações do corretor<input type="text" name="notes" id="property_notes" placeholder="Ex.: gostou da varanda, mas achou o condomínio alto"></label>
                <button type="submit" class="primary">Salvar vínculo</button>
            </form>
        </details>
    </section>

    <section id="related-properties" aria-labelledby="related-properties-title" style="margin-top:1.75rem;padding-top:1.25rem;border-top:1px solid var(--pico-muted-border-color);">
        <header style="margin-bottom:1rem;">
            <h2 id="related-properties-title" style="margin:0;font-size:1.15rem;">Imóveis relacionados</h2>
            <p style="margin:.25rem 0 0;color:var(--pico-muted-color);font-size:.85rem;">Sugestões automáticas por região e preço. Elas não criam um vínculo até você confirmá-lo.</p>
        </header>

        <details id="property-search" style="margin-bottom:1rem;">
            <summary>Preferências de busca</summary>
            <form action="/leads/<?= $e((string)$lead['id']) ?>/property-search" method="POST" style="margin-top:1rem;">
                <?= $csrfField ?>
                <label for="search_regions">Bairros ou regiões <small id="search-regions-help">Separe vários por vírgula. Em outras cidades, a correspondência considera a cidade.</small><input id="search_regions" name="search_regions" maxlength="500" aria-describedby="search-regions-help" value="<?= $e($propertySearch['manual_regions'] ?? '') ?>" placeholder="Ex.: Icaraí, Centro"></label>
            <div class="grid lead-property-form-grid">
                    <label for="search_price_min">Preço mínimo<input id="search_price_min" name="search_price_min" type="number" min="0" step="0.01" value="<?= $e($propertySearch['manual_price_min'] === null ? '' : (string)$propertySearch['manual_price_min']) ?>" placeholder="R$"></label>
                    <label for="search_price_max">Preço máximo<input id="search_price_max" name="search_price_max" type="number" min="0" step="0.01" value="<?= $e($propertySearch['manual_price_max'] === null ? '' : (string)$propertySearch['manual_price_max']) ?>" placeholder="R$"></label>
                </div>
                <button type="submit" class="secondary">Salvar preferências</button>
            </form>
        </details>

        <?php if (($propertySearch['region_source'] ?? '') === 'inferred' && !empty($propertySearch['regions'])): ?><p><small>Região sugerida a partir dos imóveis vinculados: <?= $e(implode(', ', $propertySearch['regions'])) ?>.</small></p><?php endif; ?>
        <?php if (($propertySearch['price_source'] ?? '') === 'inferred' && $propertySearch['price_min'] !== null): ?><p><small>Faixa de preço derivada dos imóveis vinculados: <?= $propertySearch['price_min'] === $propertySearch['price_max'] ? 'R$ ' . number_format((float)$propertySearch['price_min'], 2, ',', '.') : 'R$ ' . number_format((float)$propertySearch['price_min'], 2, ',', '.') . ' a R$ ' . number_format((float)$propertySearch['price_max'], 2, ',', '.') ?>.</small></p><?php endif; ?>

        <?php if (empty($propertySuggestions)): ?>
            <p role="status" style="color:var(--pico-muted-color);margin-bottom:0;"><?= $e($propertySearch['message'] ?? 'Não há sugestões para exibir.') ?></p>
        <?php else: ?>
            <div class="lead-properties-table-scroll" role="region" aria-label="Tabela de imóveis relacionados sugeridos" aria-describedby="related-properties-scroll-hint" tabindex="0" style="overflow-x:auto;">
                <p class="properties-scroll-hint lead-table-scroll-hint" id="related-properties-scroll-hint">Em telas estreitas, os dados de cada imóvel aparecem agrupados em um cartão.</p>
                <table class="table-compact lead-properties-suggestions-table property-records-table" style="margin-bottom:0;">
                    <thead><tr><th scope="col">Código</th><th scope="col">Imóvel</th><th scope="col">Região</th><th scope="col">Preço</th><th scope="col">Por que apareceu</th><th scope="col">Ação</th></tr></thead>
                    <tbody>
                    <?php foreach ($propertySuggestions as $suggestion): ?>
                        <tr>
                            <td data-label="Código"><strong><?= $e($suggestion['code']) ?></strong></td>
                            <td data-label="Imóvel"><?= $e($suggestion['title']) ?><small style="display:block;color:var(--pico-muted-color);"><?= $e(ucfirst($suggestion['type'])) ?></small></td>
                            <td data-label="Região"><?= $e(implode(', ', array_filter([$suggestion['neighborhood'] ?? '', $suggestion['city'] ?? '']))) ?></td>
                            <td data-label="Preço">R$ <?= $e(number_format((float)$suggestion['suggestion_price'], 2, ',', '.')) ?></td>
                            <td data-label="Por que apareceu"><small><?= $e($suggestion['region_reason']) ?><br><?= $e($suggestion['price_reason']) ?></small></td>
                            <td data-label="Ação"><form action="/leads/<?= $e((string)$lead['id']) ?>/properties" method="POST" style="margin:0;"><?= $csrfField ?><input type="hidden" name="property_id" value="<?= $e((string)$suggestion['id']) ?>"><input type="hidden" name="interest_type" value="interesse"><button type="submit" class="outline lead-property-action" style="margin:0;white-space:nowrap;">Vincular</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</article>
