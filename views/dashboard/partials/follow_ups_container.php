<?php
/**
 * @var list<array<string, mixed>> $followUps
 * @var array{overdue: int, today: int, future: int, completed_total: int} $counts
 * @var array<string, string> $filters
 * @var list<array<string, mixed>> $brokers
 * @var array<string, string> $stages
 * @var array<string, mixed> $currentUser
 * @var string $userRole
 * @var callable $e
 * @var callable $formatDate
 * @var callable $formatTime
 * @var callable $humanDiff
 * @var callable $getFollowUpStatus
 * @var callable $getStatusLabel
 * @var callable $getStatusBadgeClass
 * @var callable $getStageBadge
 * @var callable $getStageLabel
 * @var string $csrfField
 */

$currentSituation = $filters['situation'] ?? 'today';
$currentUserId = $filters['user_id'] ?? ($userRole === 'corretor' ? 'mine' : 'all');
?>

<div id="follow-ups-wrapper">
    <!-- Cards de Resumo Rápido e Navegação por Situação -->
    <div class="grid" style="margin-bottom: 1.75rem;">
        <!-- Card 1: Atrasados (Vencidos) -->
        <div class="stat-card stat-card-overdue" aria-label="Resumo de retornos atrasados">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">
                    <?= $e((string)$counts['overdue']) ?>
                </h3>
                <span class="badge badge-overdue" style="font-size: 0.7rem;">Urgente</span>
            </div>
            <p style="margin-top: 0.4rem; text-align: left; font-weight: 500;">Retornos Atrasados</p>
            <small style="color: var(--pico-muted-color); font-size: 0.75rem; display: block; text-align: left;">
                <?= $counts['overdue'] > 0 ? 'Exigem ação imediata da equipe' : 'Nenhum contato pendente em atraso' ?>
            </small>
        </div>

        <!-- Card 2: Para Hoje -->
        <div class="stat-card stat-card-today" aria-label="Resumo de retornos para hoje">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">
                    <?= $e((string)$counts['today']) ?>
                </h3>
                <span class="badge badge-today" style="font-size: 0.7rem;">Do Dia</span>
            </div>
            <p style="margin-top: 0.4rem; text-align: left; font-weight: 500;">Contatos de Hoje</p>
            <small style="color: var(--pico-muted-color); font-size: 0.75rem; display: block; text-align: left;">
                Programados para a jornada de hoje
            </small>
        </div>

        <!-- Card 3: Futuros -->
        <div class="stat-card stat-card-future" aria-label="Resumo de retornos futuros">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">
                    <?= $e((string)$counts['future']) ?>
                </h3>
                <span class="badge badge-future" style="font-size: 0.7rem;">Próximos</span>
            </div>
            <p style="margin-top: 0.4rem; text-align: left; font-weight: 500;">Retornos Futuros</p>
            <small style="color: var(--pico-muted-color); font-size: 0.75rem; display: block; text-align: left;">
                Agendamentos para os próximos dias
            </small>
        </div>

        <!-- Card 4: Concluídos -->
        <div class="stat-card stat-card-completed" aria-label="Resumo de retornos concluídos">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">
                    <?= $e((string)$counts['completed_total']) ?>
                </h3>
                <span class="badge badge-completed" style="font-size: 0.7rem;">Histórico</span>
            </div>
            <p style="margin-top: 0.4rem; text-align: left; font-weight: 500;">Retornos Concluídos</p>
            <small style="color: var(--pico-muted-color); font-size: 0.75rem; display: block; text-align: left;">
                Contatos com resultado registrado
            </small>
        </div>
    </div>

    <!-- Barra de Filtros Operacionais com HTMX -->
    <article style="padding: 1.25rem; margin-bottom: 1.5rem; border: 1px solid var(--pico-muted-border-color);">
        <form id="follow-ups-filter-form" 
              method="GET" 
              action="/" 
              hx-get="/" 
              hx-target="#follow-ups-wrapper"
              hx-swap="outerHTML"
              hx-indicator="#dashboard-loading"
              hx-push-url="true">
            <input type="hidden" name="partial" value="follow-ups">

            <!-- Abas Rápidas de Situação -->
            <div role="group" aria-label="Filtrar por situação de retorno" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: center;">
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--pico-muted-color); margin-right: 0.25rem;">
                    Situação:
                </span>

                <button type="button" 
                        class="<?= $currentSituation === 'overdue' ? 'contrast' : 'outline secondary' ?>" 
                        aria-pressed="<?= $currentSituation === 'overdue' ? 'true' : 'false' ?>"
                        <?= $currentSituation === 'overdue' ? 'aria-current="true"' : '' ?>
                        style="padding: 0.35rem 0.75rem; font-size: 0.8rem; margin: 0; width: auto;"
                        onclick="document.getElementById('situation-input').value = 'overdue'; document.getElementById('follow-ups-filter-form').requestSubmit();">
                    Atrasados
                </button>

                <button type="button" 
                        class="<?= $currentSituation === 'today' ? 'contrast' : 'outline secondary' ?>" 
                        aria-pressed="<?= $currentSituation === 'today' ? 'true' : 'false' ?>"
                        <?= $currentSituation === 'today' ? 'aria-current="true"' : '' ?>
                        style="padding: 0.35rem 0.75rem; font-size: 0.8rem; margin: 0; width: auto;"
                        onclick="document.getElementById('situation-input').value = 'today'; document.getElementById('follow-ups-filter-form').requestSubmit();">
                    Hoje
                </button>

                <button type="button" 
                        class="<?= $currentSituation === 'future' ? 'contrast' : 'outline secondary' ?>" 
                        aria-pressed="<?= $currentSituation === 'future' ? 'true' : 'false' ?>"
                        <?= $currentSituation === 'future' ? 'aria-current="true"' : '' ?>
                        style="padding: 0.35rem 0.75rem; font-size: 0.8rem; margin: 0; width: auto;"
                        onclick="document.getElementById('situation-input').value = 'future'; document.getElementById('follow-ups-filter-form').requestSubmit();">
                    Futuros
                </button>

                <button type="button" 
                        class="<?= $currentSituation === 'completed' ? 'contrast' : 'outline secondary' ?>" 
                        aria-pressed="<?= $currentSituation === 'completed' ? 'true' : 'false' ?>"
                        <?= $currentSituation === 'completed' ? 'aria-current="true"' : '' ?>
                        style="padding: 0.35rem 0.75rem; font-size: 0.8rem; margin: 0; width: auto;"
                        onclick="document.getElementById('situation-input').value = 'completed'; document.getElementById('follow-ups-filter-form').requestSubmit();">
                    Concluídos
                </button>

                <button type="button" 
                        class="<?= $currentSituation === 'all' ? 'contrast' : 'outline secondary' ?>" 
                        aria-pressed="<?= $currentSituation === 'all' ? 'true' : 'false' ?>"
                        <?= $currentSituation === 'all' ? 'aria-current="true"' : '' ?>
                        style="padding: 0.35rem 0.75rem; font-size: 0.8rem; margin: 0; width: auto;"
                        onclick="document.getElementById('situation-input').value = 'all'; document.getElementById('follow-ups-filter-form').requestSubmit();">
                    Todos
                </button>

                <input type="hidden" name="situation" id="situation-input" value="<?= $e($currentSituation) ?>">
            </div>

            <!-- Grade de Filtros de Período, Corretor e Estágio -->
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 0;">
                <!-- Filtro de Corretor Responsável -->
                <div>
                    <label for="filter-broker" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem;">
                        Responsável pelo Retorno
                    </label>
                    <select name="user_id" id="filter-broker" style="margin: 0; font-size: 0.85rem;" onchange="this.form.requestSubmit()">
                        <?php if ($userRole === 'corretor'): ?>
                            <option value="mine" <?= $currentUserId === 'mine' ? 'selected' : '' ?>>Meus Retornos (Padrão)</option>
                            <option value="all" <?= $currentUserId === 'all' ? 'selected' : '' ?>>Toda a Equipe (Colaborativo)</option>
                        <?php else: ?>
                            <option value="all" <?= $currentUserId === 'all' ? 'selected' : '' ?>>Todos os Corretores</option>
                            <option value="mine" <?= $currentUserId === 'mine' ? 'selected' : '' ?>>Meus Retornos</option>
                        <?php endif; ?>

                        <?php foreach ($brokers as $b): ?>
                            <option value="<?= $e((string)$b['id']) ?>" <?= $currentUserId === (string)$b['id'] ? 'selected' : '' ?>>
                                <?= $e($b['name']) ?> (<?= $e($b['role_label']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro de Estágio do Lead -->
                <div>
                    <label for="filter-stage" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem;">
                        Estágio do Lead
                    </label>
                    <select name="stage" id="filter-stage" style="margin: 0; font-size: 0.85rem;" onchange="this.form.requestSubmit()">
                        <option value="">Todos os estágios</option>
                        <?php foreach ($stages as $stageKey => $stageLabel): ?>
                            <option value="<?= $e($stageKey) ?>" <?= ($filters['stage'] ?? '') === $stageKey ? 'selected' : '' ?>>
                                <?= $e($stageLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Período: De -->
                <div>
                    <label for="filter-date-from" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem;">
                        Data Inicial
                    </label>
                    <input type="date" 
                           name="date_from" 
                           id="filter-date-from" 
                           value="<?= $e($filters['date_from'] ?? '') ?>" 
                           style="margin: 0; font-size: 0.85rem;"
                           onchange="this.form.requestSubmit()">
                </div>

                <!-- Período: Até -->
                <div>
                    <label for="filter-date-to" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem;">
                        Data Final
                    </label>
                    <input type="date" 
                           name="date_to" 
                           id="filter-date-to" 
                           value="<?= $e($filters['date_to'] ?? '') ?>" 
                           style="margin: 0; font-size: 0.85rem;"
                           onchange="this.form.requestSubmit()">
                </div>

                <!-- Busca por texto livre -->
                <div>
                    <label for="filter-q" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem;">
                        Buscar Lead
                    </label>
                    <input type="search" 
                           name="q" 
                           id="filter-q" 
                           placeholder="Nome, fone ou nota..." 
                           value="<?= $e($filters['q'] ?? '') ?>" 
                           style="margin: 0; font-size: 0.85rem;"
                           hx-trigger="input changed delay:350ms, search">
                </div>

                <!-- Ações do Filtro -->
                <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                    <button type="submit" class="primary" style="margin: 0; padding: 0.45rem 0.85rem; font-size: 0.85rem; width: 100%;">
                        Filtrar
                    </button>
                    <a href="/?situation=today" 
                       role="button" 
                       class="outline secondary" 
                       style="margin: 0; padding: 0.45rem 0.85rem; font-size: 0.85rem; white-space: nowrap;"
                       title="Restaurar padrão da dashboard">
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </article>

    <!-- Indicador de carregamento HTMX com live region acessível -->
    <div id="dashboard-loading" class="htmx-indicator" role="status" aria-live="polite" style="margin-bottom: 1rem;">
        <svg class="spinner-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 2a10 10 0 0 1 10 10"></path>
        </svg>
        <span>Atualizando agenda de retornos...</span>
    </div>

    <!-- Banner de Resiliência de Erro HTMX / Conexão -->
    <div id="dashboard-error" class="alert alert-error" role="alert" style="display: none; margin-bottom: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span id="dashboard-error-message">Erro ao carregar os dados. Verifique sua conexão.</span>
            <button type="button" 
                    class="outline secondary" 
                    style="margin: 0; padding: 0.25rem 0.65rem; font-size: 0.8rem; width: auto;" 
                    onclick="document.getElementById('follow-ups-filter-form').requestSubmit();">
                ↻ Tentar Novamente
            </button>
        </div>
    </div>

    <?php if (!empty($dashboardNotice)): ?>
        <div class="alert alert-<?= ($dashboardNotice['type'] ?? '') === 'success' ? 'success' : 'error' ?>" role="<?= ($dashboardNotice['type'] ?? '') === 'success' ? 'status' : 'alert' ?>" aria-live="polite">
            <?= $e($dashboardNotice['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <!-- Lista de Contatos / Agenda de Follow-ups -->
    <?php if (empty($followUps)): ?>
        <div class="empty-state-box">
            <?php if ($currentSituation === 'overdue'): ?>
                <svg class="empty-state-icon empty-state-icon-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <h4 style="margin-bottom: 0.5rem; color: #166534;">Nenhum retorno atrasado!</h4>
                <p style="color: var(--pico-muted-color); font-size: 0.9rem; margin: 0;">
                    Excelente trabalho! Todos os contatos pendentes estão rigorosamente em dia.
                </p>
            <?php elseif ($currentSituation === 'today'): ?>
                <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <h4 style="margin-bottom: 0.5rem;">Nenhum retorno programado para hoje</h4>
                <p style="color: var(--pico-muted-color); font-size: 0.9rem; margin: 0 0 1rem 0;">
                    Você não possui retornos agendados para a data de hoje.
                </p>
                <a href="/leads" role="button" class="outline" style="font-size: 0.85rem;">
                    Explorar Leads em Prospecção &rarr;
                </a>
            <?php else: ?>
                <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <h4 style="margin-bottom: 0.5rem;">Nenhum resultado encontrado</h4>
                <p style="color: var(--pico-muted-color); font-size: 0.9rem; margin: 0;">
                    Não encontramos retornos correspondentes aos filtros selecionados. Tente ajustar o período ou o corretor.
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <p style="margin: 0; font-size: 0.9rem; color: var(--pico-muted-color);">
                Exibindo <strong><?= count($followUps) ?></strong> retorno(s) na visualização atual:
            </p>
        </div>

        <div class="follow-ups-list">
            <?php foreach ($followUps as $item): ?>
                <?php
                    $statusKey = $getFollowUpStatus($item['scheduled_at'], $item['status']);
                    $cleanPhone = preg_replace('/\D+/', '', $item['lead_phone'] ?? '') ?? '';
                    $isItemCompleted = $item['status'] === 'completed';
                    $modalIdComplete = 'modal-complete-' . $item['id'];
                    $modalIdReschedule = 'modal-reschedule-' . $item['id'];
                ?>
                <div class="follow-up-card is-<?= $e($statusKey) ?>">
                    <div class="follow-up-grid">
                        <!-- Coluna 1: Dados do Lead & Contato (Harden: min-width e quebra de palavras) -->
                        <div style="min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                <a href="/leads/<?= $e((string)$item['lead_id']) ?>" style="font-size: 1.05rem; font-weight: 700; text-decoration: none; word-break: break-word; overflow-wrap: anywhere;">
                                    <?= $e($item['lead_name']) ?>
                                </a>
                                <span class="badge <?= $e($getStageBadge($item['lead_stage'])) ?>" style="font-size: 0.7rem; white-space: nowrap;">
                                    <?= $e($getStageLabel($item['lead_stage'])) ?>
                                </span>
                                <span class="badge <?= $e($getStatusBadgeClass($statusKey)) ?>" style="font-size: 0.7rem; white-space: nowrap;">
                                    <?= $e($getStatusLabel($statusKey)) ?>
                                </span>
                                <?php if (!empty($item['target_property'])): ?>
                                    <span class="badge badge-neutral" style="font-size: 0.7rem; white-space: nowrap;" title="Imóvel vinculado">
                                        🏠 <?= $e($item['target_property']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--pico-muted-color); word-break: break-word;">
                                <?php if (!empty($item['lead_phone'])): ?>
                                    <span>Tel: <strong><?= $e($item['lead_phone']) ?></strong></span>
                                <?php endif; ?>
                                <?php if (!empty($item['lead_email'])): ?>
                                    &bull; <span><?= $e($item['lead_email']) ?></span>
                                <?php endif; ?>
                            </p>

                            <!-- Micro Ações com Alvo de Toque Ergonômico (min 44x44px) -->
                            <?php if (!empty($cleanPhone)): ?>
                                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                                    <a href="tel:<?= $e($cleanPhone) ?>" class="outline btn-touch-target" style="font-size: 0.8rem; padding: 0.35rem 0.65rem; min-height: 40px; text-decoration: none;" title="Ligar para o lead" aria-label="Ligar para <?= $e($item['lead_name']) ?>">
                                        📞 Ligar
                                    </a>
                                    <a href="https://wa.me/55<?= $e($cleanPhone) ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp btn-touch-target" style="font-size: 0.8rem; padding: 0.35rem 0.65rem; min-height: 40px; text-decoration: none;" title="WhatsApp com o lead" aria-label="Abrir WhatsApp com <?= $e($item['lead_name']) ?>">
                                        💬 WhatsApp
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Coluna 2: Responsável & Horário Programado -->
                        <div>
                            <span style="display: block; font-size: 0.75rem; color: var(--pico-muted-color);">Horário Previsto:</span>
                            <strong style="font-size: 0.95rem; color: <?= $statusKey === 'overdue' ? '#dc2626' : 'inherit' ?>;">
                                <?= $e($formatDate($item['scheduled_at'])) ?>
                            </strong>
                            <small style="display: block; font-size: 0.75rem; color: var(--pico-muted-color);">
                                <?= $e($humanDiff($item['scheduled_at'], $item['status'])) ?>
                            </small>

                            <div style="margin-top: 0.4rem;">
                                <span style="font-size: 0.75rem; color: var(--pico-muted-color);">Responsável:</span>
                                <span style="font-size: 0.85rem; font-weight: 600; display: block;">
                                    <?= $e($item['broker_name']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Coluna 3: Objetivo / Observação do Contato -->
                        <div style="min-width: 0;">
                            <span style="display: block; font-size: 0.75rem; color: var(--pico-muted-color);">Objetivo do Retorno:</span>
                            <p style="margin: 0; font-size: 0.85rem; color: var(--pico-color); white-space: pre-wrap; word-break: break-word; overflow-wrap: break-word; max-height: 4.5rem; overflow-y: auto;">
                                <?= $e($item['notes'] ?? 'Sem observações adicionais.') ?>
                            </p>

                            <?php if ($isItemCompleted && !empty($item['result_notes'])): ?>
                                <div style="margin-top: 0.5rem; padding: 0.4rem 0.6rem; background: var(--pico-card-background-color); border: 1px solid var(--pico-muted-border-color); border-radius: var(--pico-border-radius); word-break: break-word;">
                                    <span style="font-size: 0.75rem; font-weight: 600; color: var(--pico-color); display: block;">Resultado Registrado:</span>
                                    <small style="font-size: 0.8rem;"><?= $e($item['result_notes']) ?></small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Coluna 4: Botões de Ação Operacional & Atalhos 1-Clique -->
                        <div style="text-align: right; display: flex; flex-direction: column; gap: 0.35rem; justify-content: center; min-width: 140px;">
                            <?php if (!$isItemCompleted): ?>
                                <!-- Botão Concluir Retorno -->
                                <button type="button" 
                                        class="primary" 
                                        style="font-size: 0.8rem; padding: 0.35rem 0.65rem; margin: 0; white-space: nowrap;"
                                        onclick="openDashboardCompleteModal(this)"
                                        data-id="<?= $e((string)$item['id']) ?>"
                                        data-lead-name="<?= $e($item['lead_name']) ?>"
                                        data-scheduled-at="<?= $e($formatDate($item['scheduled_at'])) ?>">
                                    ✓ Registrar Resultado
                                </button>

                                <!-- Botão Reagendar -->
                                <button type="button" 
                                        class="outline secondary" 
                                        style="font-size: 0.8rem; padding: 0.3rem 0.65rem; margin: 0; white-space: nowrap;"
                                        onclick="openDashboardRescheduleModal(this)"
                                        data-id="<?= $e((string)$item['id']) ?>"
                                        data-lead-name="<?= $e($item['lead_name']) ?>"
                                        data-user-id="<?= $e((string)$item['user_id']) ?>">
                                    🕒 Reagendar
                                </button>

                                <!-- Micro Atalho 1-Clique: Não Atendeu (+2h) -->
                                <form action="/follow-ups/<?= $e((string)$item['id']) ?>/reschedule" 
                                      method="POST" 
                                      style="margin: 0; display: inline-block; width: 100%;" 
                                      onsubmit="if(this.dataset.submitted) return false; this.dataset.submitted='true'; this.querySelector('button').disabled=true;">
                                    <?= $csrfField ?>
                                    <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">
                                    <input type="hidden" name="scheduled_at" value="<?= date('Y-m-d\TH:i', strtotime('+2 hours')) ?>">
                                    <input type="hidden" name="reason" value="Ligação não atendida / Caixa postal. Retorno automático em 2 horas.">
                                    <button type="submit" 
                                            class="outline" 
                                            style="font-size: 0.72rem; padding: 0.2rem 0.45rem; margin: 0; width: 100%; white-space: nowrap;" 
                                            title="Reagendar retorno automaticamente para daqui a 2 horas sem abrir modal">
                                        📞 Não Atendeu (+2h)
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-completed" style="font-size: 0.75rem; text-align: center;">
                                    ✓ Concluído em <?= $e($formatDate($item['completed_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- =======================================================================
         MODAIS COMPARTILHADOS DINÂMICOS (Elimina Inchaço de DOM no HTMX)
         ======================================================================= -->
    <!-- MODAL COMPARTILHADO: REGISTRAR RESULTADO DO CONTATO (CONCLUIR) -->
    <dialog id="modal-dashboard-complete" aria-labelledby="title-dashboard-complete" aria-modal="true">
        <article style="max-width: 550px; text-align: left;">
            <header style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 id="title-dashboard-complete" style="margin: 0; font-size: 1.25rem;">Registrar Resultado do Contato</h3>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--pico-muted-color);">
                        Lead: <strong id="modal-complete-lead-name">—</strong> &bull; Previsto: <span id="modal-complete-scheduled-at">—</span>
                    </p>
                </div>
                <button type="button" aria-label="Fechar janela" class="close" onclick="document.getElementById('modal-dashboard-complete').close();">&times;</button>
            </header>

            <form id="form-dashboard-complete" 
                  action="" 
                  method="POST" 
                  hx-include="#follow-ups-filter-form [name]:not([name='user_id'])"
                  hx-target="#follow-ups-wrapper"
                  hx-swap="outerHTML"
                  hx-indicator="#dashboard-loading"
                  hx-disabled-elt="find button[type='submit']"
                  onsubmit="if (!this.checkValidity()) return false; if (this.dataset.submitted) return false; this.dataset.submitted='true';">
                <?= $csrfField ?>
                <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">

                <!-- Canal Utilizado e Data do Contato -->
                <div class="grid" style="margin-bottom: 0.75rem;">
                    <div>
                        <label for="complete-type-shared" style="font-size: 0.85rem; font-weight: 600;">
                            Canal de Contato <span aria-hidden="true">*</span>
                        </label>
                        <select name="type" id="complete-type-shared" required aria-required="true">
                            <option value="ligacao">📞 Ligação Telefônica</option>
                            <option value="whatsapp">💬 WhatsApp</option>
                            <option value="visita">🏠 Visita Presencial</option>
                            <option value="reuniao">👥 Reunião Presencial/Online</option>
                            <option value="email">✉️ E-mail</option>
                            <option value="outro">Outro Canal</option>
                        </select>
                    </div>

                    <div>
                        <label for="complete-contacted-at-shared" style="font-size: 0.85rem; font-weight: 600;">
                            Data / Hora Realizada <span aria-hidden="true">*</span>
                        </label>
                        <input type="datetime-local" 
                               name="contacted_at" 
                               id="complete-contacted-at-shared" 
                               value="<?= date('Y-m-d\TH:i') ?>" 
                               max="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>"
                               required
                               aria-required="true">
                    </div>
                </div>

                <!-- Resultado Obrigatório -->
                <label for="result-notes-shared" style="font-size: 0.85rem; font-weight: 600;">
                    Resultado do Contato (Obrigatório) <span aria-hidden="true">*</span>
                    <textarea name="result_notes" 
                              id="result-notes-shared" 
                              rows="3" 
                              placeholder="Descreva o que foi conversado com o cliente, interesse, objeções ou próximos passos..." 
                              required 
                              minlength="2"
                              maxlength="2000"
                              aria-required="true"></textarea>
                </label>

                <!-- Opção de Já Agendar o Próximo Follow-up -->
                <details style="margin: 1rem 0; border: 1px solid var(--pico-muted-border-color); padding: 0.75rem; border-radius: var(--pico-border-radius);">
                    <summary style="font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                        + Agendar Próximo Retorno para este Lead
                    </summary>

                    <div style="margin-top: 0.75rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; margin-bottom: 0.75rem;">
                            <input type="checkbox" name="schedule_next" value="1" onchange="document.getElementById('next-schedule-fields-shared').style.display = this.checked ? 'block' : 'none';">
                            <span>Sim, agendar uma nova data de retorno</span>
                        </label>

                        <div id="next-schedule-fields-shared" style="display: none;">
                            <div class="grid" style="margin-bottom: 0.5rem;">
                                <div>
                                    <label for="next-scheduled-at-shared" style="font-size: 0.8rem; font-weight: 600;">
                                        Nova Data / Hora
                                    </label>
                                    <input type="datetime-local" 
                                           name="next_scheduled_at" 
                                           id="next-scheduled-at-shared"
                                           min="<?= date('Y-m-d\TH:i') ?>">
                                </div>
                                <div>
                                    <label for="next-user-id-shared" style="font-size: 0.8rem; font-weight: 600;">
                                        Corretor Responsável
                                    </label>
                                    <select name="next_user_id" id="next-user-id-shared">
                                        <?php foreach ($brokers as $b): ?>
                                            <option value="<?= $e((string)$b['id']) ?>">
                                                <?= $e($b['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <label for="next-notes-shared" style="font-size: 0.8rem; font-weight: 600;">
                                Objetivo do Próximo Retorno
                                <input type="text" 
                                       name="next_notes" 
                                       id="next-notes-shared" 
                                       placeholder="Ex: Cobrar resposta sobre proposta de financiamento"
                                       maxlength="255">
                            </label>
                        </div>
                    </div>
                </details>

                <footer style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                    <button type="button" class="outline secondary" onclick="document.getElementById('modal-dashboard-complete').close();" style="width: auto; margin: 0;">
                        Cancelar
                    </button>
                    <button type="submit" class="primary" style="width: auto; margin: 0;">
                        Salvar Resultado & Concluir
                    </button>
                </footer>
            </form>
        </article>
    </dialog>

    <!-- MODAL COMPARTILHADO: REAGENDAR FOLLOW-UP -->
    <dialog id="modal-dashboard-reschedule" aria-labelledby="title-dashboard-reschedule" aria-modal="true">
        <article style="max-width: 500px; text-align: left;">
            <header style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 id="title-dashboard-reschedule" style="margin: 0; font-size: 1.25rem;">Reagendar Retorno</h3>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--pico-muted-color);">
                        Lead: <strong id="modal-reschedule-lead-name">—</strong>
                    </p>
                </div>
                <button type="button" aria-label="Fechar janela" class="close" onclick="document.getElementById('modal-dashboard-reschedule').close();">&times;</button>
            </header>

            <form id="form-dashboard-reschedule" 
                  action="" 
                  method="POST" 
                  hx-include="#follow-ups-filter-form [name]:not([name='user_id'])"
                  hx-target="#follow-ups-wrapper"
                  hx-swap="outerHTML"
                  hx-indicator="#dashboard-loading"
                  hx-disabled-elt="find button[type='submit']"
                  onsubmit="if (!this.checkValidity()) return false; if (this.dataset.submitted) return false; this.dataset.submitted='true';">
                <?= $csrfField ?>
                <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">

                <div class="grid" style="margin-bottom: 0.75rem;">
                    <div>
                        <label for="reschedule-date-shared" style="font-size: 0.85rem; font-weight: 600;">
                            Nova Data e Horário <span aria-hidden="true">*</span>
                        </label>
                        <input type="datetime-local" 
                               name="scheduled_at" 
                               id="reschedule-date-shared" 
                               min="<?= date('Y-m-d\TH:i') ?>"
                               required
                               aria-required="true">
                    </div>

                    <div>
                        <label for="reschedule-user-shared" style="font-size: 0.85rem; font-weight: 600;">
                            Corretor Responsável
                        </label>
                        <select name="user_id" id="reschedule-user-shared">
                            <?php foreach ($brokers as $b): ?>
                                <option value="<?= $e((string)$b['id']) ?>">
                                    <?= $e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label for="reschedule-reason-shared" style="font-size: 0.85rem; font-weight: 600;">
                    Motivo do Reagendamento <span aria-hidden="true">*</span>
                    <input type="text" 
                           name="reason" 
                           id="reschedule-reason-shared" 
                           placeholder="Ex: Cliente ocupado em reunião / Solicitou retorno amanhã às 14h" 
                           required 
                           minlength="3"
                           maxlength="255"
                           aria-required="true">
                </label>

                <footer style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                    <button type="button" class="outline secondary" onclick="document.getElementById('modal-dashboard-reschedule').close();" style="width: auto; margin: 0;">
                        Cancelar
                    </button>
                    <button type="submit" class="primary" style="width: auto; margin: 0;">
                        Confirmar Reagendamento
                    </button>
                </footer>
            </form>
        </article>
    </dialog>

    <script>
        function openDashboardCompleteModal(btn) {
            const modal = document.getElementById('modal-dashboard-complete');
            if (!modal) return;
            const form = modal.querySelector('form');
            const action = dashboardFollowUpAction(btn.dataset.id, 'complete');
            form.action = action;
            form.setAttribute('hx-post', action);
            form.dataset.submitted = '';
            window.htmx?.process(form);
            modal.querySelector('#modal-complete-lead-name').textContent = btn.dataset.leadName || 'Lead';
            modal.querySelector('#modal-complete-scheduled-at').textContent = btn.dataset.scheduledAt || '';
            modal.showModal();
        }

        function openDashboardRescheduleModal(btn) {
            const modal = document.getElementById('modal-dashboard-reschedule');
            if (!modal) return;
            const form = modal.querySelector('form');
            const action = dashboardFollowUpAction(btn.dataset.id, 'reschedule');
            form.action = action;
            form.setAttribute('hx-post', action);
            form.dataset.submitted = '';
            window.htmx?.process(form);
            modal.querySelector('#modal-reschedule-lead-name').textContent = btn.dataset.leadName || 'Lead';
            const userSelect = modal.querySelector('#reschedule-user-shared');
            if (userSelect && btn.dataset.userId) {
                userSelect.value = btn.dataset.userId;
            }
            modal.showModal();
        }

        function dashboardFollowUpAction(id, action) {
            const filters = document.getElementById('follow-ups-filter-form');
            const params = new URLSearchParams();
            if (filters) {
                new FormData(filters).forEach((value, key) => {
                    params.append(key === 'user_id' ? 'filter_user_id' : key, value);
                });
            }
            const query = params.toString();
            const endpoint = '/follow-ups/' + encodeURIComponent(id) + '/' + action;
            return query ? endpoint + '?' + query : endpoint;
        }

        if (!window.dashboardFollowUpRequestHandlerInstalled) {
            window.dashboardFollowUpRequestHandlerInstalled = true;
            document.body.addEventListener('htmx:afterRequest', function (event) {
                const form = event.detail.elt;
                if (!form || !form.matches('#form-dashboard-complete, #form-dashboard-reschedule') || event.detail.successful) return;
                form.dataset.submitted = '';
            });
        }
    </script>
</div>
