<?php
$dateFrom = $filters['date_from'] ?? '';
$dateTo = $filters['date_to'] ?? '';
$selectedStage = $filters['stage'] ?? '';
$leadsUrl = static function (array $overrides = []) use ($dateFrom, $dateTo, $selectedStage): string {
    $params = array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'stage' => $selectedStage]);
    $params = array_merge($params, $overrides);
    return '/dashboard/leads' . ($params ? '?' . http_build_query($params) : '');
};
$leadName = static fn(array $lead): string => trim((string)($lead['name'] ?? '')) ?: 'Lead sem nome';
$metricStages = ['proposta' => 'Proposta', 'ganho' => 'Fechados', 'perdido' => 'Perdidos'];
?>
<section id="leads-dashboard-content" class="dashboard-content" aria-label="Indicadores e lista de Leads">
    <?php if (!empty($errors)): ?>
        <div class="dashboard-filter-notices" role="status"><p>Alguns filtros foram ignorados:</p><ul><?php foreach ($errors as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form class="dashboard-filters" method="get" action="/dashboard/leads" hx-get="/dashboard/leads" hx-target="#leads-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#leads-dashboard-loading">
        <label>Data inicial
            <input type="date" name="date_from" value="<?= $e($dateFrom) ?>">
        </label>
        <label>Data final
            <input type="date" name="date_to" value="<?= $e($dateTo) ?>">
        </label>
        <label>Estágio da lista
            <select name="stage">
                <option value="">Todos os estágios</option>
                <?php foreach ($stages as $key => $label): ?>
                    <option value="<?= $e($key) ?>" <?= $selectedStage === $key ? 'selected' : '' ?>><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="dashboard-filter-actions">
            <button type="submit">Aplicar filtros</button>
            <a href="/dashboard/leads" role="button" class="outline secondary">Limpar</a>
        </div>
    </form>

    <section class="dashboard-panel" aria-labelledby="leads-metrics-title">
        <header class="dashboard-section-heading">
            <div><h2 id="leads-metrics-title">Pipeline atual</h2><p>O período filtra pela data de criação; o estágio exibido é o atual.</p></div>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?><span class="dashboard-period"><?= $dateFrom !== '' ? $e($dateFrom) : 'Início' ?> – <?= $dateTo !== '' ? $e($dateTo) : 'Hoje' ?></span><?php else: ?><span class="dashboard-period">Todo o histórico</span><?php endif; ?>
        </header>
        <?php if (!empty($metrics_error)): ?>
            <div class="dashboard-inline-error" role="alert"><p>Os indicadores do pipeline não puderam ser carregados.</p><a href="<?= $e($leadsUrl()) ?>" hx-get="<?= $e($leadsUrl()) ?>" hx-target="#leads-dashboard-content" hx-swap="outerHTML" hx-indicator="#leads-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
        <?php else: ?>
            <nav class="dashboard-stage-metrics" aria-label="Filtrar lista pelo estágio de negócio">
                <?php foreach ($metricStages as $stageKey => $label): ?>
                    <?php $count = $stage_counts[$stageKey] ?? 0; $isSelected = $selectedStage === $stageKey; ?>
                    <a href="<?= $e($leadsUrl(['stage' => $isSelected ? '' : $stageKey])) ?>" <?= $isSelected ? 'aria-current="page"' : '' ?>>
                        <span><?= $e($label) ?></span><strong><?= $count === null ? '—' : $e((string)$count) ?></strong>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="leads-list-title">
        <header class="dashboard-section-heading"><div><h2 id="leads-list-title">Leads correspondentes</h2><p><?= (int)$leads['total'] ?> registro(s), até <?= (int)$leads['per_page'] ?> por página.</p></div></header>
        <?php if (!empty($list_error)): ?>
            <div class="dashboard-inline-error" role="alert"><p>A lista de Leads não pôde ser carregada.</p><a href="<?= $e($leadsUrl(['stage' => $selectedStage])) ?>" hx-get="<?= $e($leadsUrl(['stage' => $selectedStage])) ?>" hx-target="#leads-dashboard-content" hx-swap="outerHTML" hx-indicator="#leads-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
        <?php elseif (empty($leads['items'])): ?>
            <div class="dashboard-empty-state">
                <h3><?= $dateFrom === '' && $dateTo === '' && $selectedStage === '' ? 'Ainda não há Leads cadastrados' : 'Nenhum Lead corresponde aos filtros' ?></h3>
                <p><?= $dateFrom === '' && $dateTo === '' && $selectedStage === '' ? 'Cadastre um Lead para começar a acompanhar o pipeline.' : 'Limpe ou ajuste os filtros para consultar outros registros.' ?></p>
                <?php if ($dateFrom === '' && $dateTo === '' && $selectedStage === ''): ?><a href="/leads/new" role="button">Cadastrar Lead</a><?php else: ?><a href="/dashboard/leads" role="button" class="outline secondary">Limpar filtros</a><?php endif; ?>
            </div>
        <?php else: ?>
            <p class="dashboard-scroll-hint"><span class="dashboard-scroll-hint-tablet">Role horizontalmente para ver todas as colunas.</span><span class="dashboard-scroll-hint-mobile">Em telas estreitas, os dados aparecem em linhas empilhadas.</span></p>
            <div class="dashboard-table-scroll" role="region" aria-label="Lista paginada de Leads" tabindex="0">
                <table class="dashboard-table">
                    <thead><tr><th scope="col">Lead</th><th scope="col">Estágio atual</th><th scope="col">Corretor</th><th scope="col">Criação</th><th scope="col">Ação</th></tr></thead>
                    <tbody>
                        <?php foreach ($leads['items'] as $lead): ?>
                            <tr>
                                <td data-label="Lead"><a href="/leads/<?= (int)$lead['id'] ?>"><?= $e($leadName($lead)) ?></a></td>
                                <td data-label="Estágio atual"><span class="badge <?= $e($getStageBadge($lead['stage'])) ?>"><?= $e($getStageLabel($lead['stage'])) ?></span></td>
                                <td data-label="Corretor"><?= $e($lead['user_name'] ?? '—') ?></td>
                                <td data-label="Criação" class="dashboard-numeric"><?= $e($formatDate($lead['created_at_local'], 'd/m/Y')) ?></td>
                                <td data-label="Ação"><a href="/leads/<?= (int)$lead['id'] ?>">Abrir ficha</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($leads['pages'] > 1): ?>
                <nav class="dashboard-pagination" aria-label="Paginação de Leads">
                    <span>Página <?= (int)$leads['page'] ?> de <?= (int)$leads['pages'] ?></span>
                    <div>
                        <?php if ($leads['page'] > 1): ?><a href="<?= $e($leadsUrl(['page' => $leads['page'] - 1])) ?>" hx-get="<?= $e($leadsUrl(['page' => $leads['page'] - 1])) ?>" hx-target="#leads-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#leads-dashboard-loading">Anterior</a><?php endif; ?>
                        <?php if ($leads['page'] < $leads['pages']): ?><a href="<?= $e($leadsUrl(['page' => $leads['page'] + 1])) ?>" hx-get="<?= $e($leadsUrl(['page' => $leads['page'] + 1])) ?>" hx-target="#leads-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#leads-dashboard-loading">Próxima</a><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>
