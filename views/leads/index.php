<?php
/**
 * @var list<array<string, mixed>> $leads
 * @var int $total
 * @var int $totalInDatabase
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var array<string, string> $filters
 * @var array<string, string> $stages
 * @var array<string, int> $stageCounts
 * @var list<array<string, mixed>> $brokers
 * @var array<string, string> $sources
 * @var callable $e
 * @var callable $formatPhone
 * @var callable $getStageBadge
 * @var callable $getStageLabel
 */
?>

<div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <h1 style="margin-bottom: 0.25rem;">Gestão de Leads</h1>
        <p style="color: var(--pico-muted-color); margin: 0; font-size: 0.95rem;">
            Módulo de Leads: acompanhe o funil de oportunidades, atribuições e transições comerciais da imobiliária.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <a href="/leads/import" role="button" class="outline secondary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Importar CSV
            </a>
        <?php endif; ?>
        <a href="/leads/new" role="button" class="primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Novo Lead
        </a>
    </div>
</div>

<!-- Barra de Resumo Rápido dos Estágios -->
<div class="filter-pills-bar" role="navigation" aria-label="Filtro rápido por estágio">
    <a href="/leads" 
       role="button" 
       class="<?= empty($filters['stage']) ? 'contrast' : 'outline secondary' ?>" 
       style="padding: 0.35rem 0.75rem; font-size: 0.8rem; white-space: nowrap; margin: 0;"
       hx-get="/leads"
       hx-target="#leads-table-container"
       hx-indicator="#leads-loading"
       hx-push-url="true">
        Todos (<?= $e((string)$totalInDatabase) ?>)
    </a>
    <?php foreach ($stages as $stageKey => $stageLabel): ?>
        <?php $count = $stageCounts[$stageKey] ?? 0; ?>
        <a href="/leads?stage=<?= $e($stageKey) ?>" 
           role="button" 
           class="<?= ($filters['stage'] ?? '') === $stageKey ? 'contrast' : 'outline secondary' ?>"
           style="padding: 0.35rem 0.75rem; font-size: 0.8rem; white-space: nowrap; margin: 0;"
           hx-get="/leads?stage=<?= $e($stageKey) ?>"
           hx-target="#leads-table-container"
           hx-indicator="#leads-loading"
           hx-push-url="true">
            <?= $e($stageLabel) ?> (<?= $e((string)$count) ?>)
        </a>
    <?php endforeach; ?>
</div>

<!-- Barra de Filtros e Busca Operacional com HTMX -->
<section class="leads-filter-toolbar">
    <form id="leads-filter-form" 
          method="GET" 
          action="/leads"
          hx-get="/leads"
          hx-target="#leads-table-container"
          hx-indicator="#leads-loading"
          hx-push-url="true">
        
        <div class="leads-filter-grid">
            <!-- Busca por texto -->
            <div>
                <input type="search" 
                       name="q" 
                       id="filter-q"
                       value="<?= $e($filters['q']) ?>" 
                       placeholder="Buscar por nome, telefone ou e-mail..." 
                       aria-label="Buscar leads"
                       hx-trigger="input changed delay:350ms, search">
            </div>

            <!-- Filtro de Estágio -->
            <div>
                <select name="stage" id="filter-stage" aria-label="Filtrar por estágio" hx-trigger="change">
                    <option value="">Todos os estágios</option>
                    <?php foreach ($stages as $key => $label): ?>
                        <option value="<?= $e($key) ?>" <?= ($filters['stage'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro de Responsável -->
            <div>
                <select name="user_id" id="filter-user-id" aria-label="Filtrar por corretor responsável" hx-trigger="change">
                    <option value="">Todos os responsáveis</option>
                    <option value="unassigned" <?= ($filters['user_id'] ?? '') === 'unassigned' ? 'selected' : '' ?>>
                        [Não atribuído]
                    </option>
                    <?php foreach ($brokers as $broker): ?>
                        <option value="<?= $e((string)$broker['id']) ?>" <?= ($filters['user_id'] ?? '') === (string)$broker['id'] ? 'selected' : '' ?>>
                            <?= $e($broker['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro de Origem -->
            <div>
                <select name="source" id="filter-source" aria-label="Filtrar por origem do lead" hx-trigger="change">
                    <option value="">Todas as origens</option>
                    <?php foreach ($sources as $sourceKey => $sourceLabel): ?>
                        <option value="<?= $e($sourceKey) ?>" <?= ($filters['source'] ?? '') === $sourceKey ? 'selected' : '' ?>>
                            <?= $e($sourceLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Limpar Filtros -->
            <div>
                <?php $hasActiveFilters = !empty($filters['q']) || !empty($filters['stage']) || !empty($filters['user_id']) || !empty($filters['source']); ?>
                <a href="/leads" 
                   role="button" 
                   class="outline secondary" 
                   style="padding: 0.45rem 0.85rem; font-size: 0.85rem; white-space: nowrap; <?= $hasActiveFilters ? '' : 'visibility: hidden;' ?>"
                   <?= $hasActiveFilters ? '' : 'tabindex="-1" aria-hidden="true"' ?>
                   hx-get="/leads"
                   hx-target="#leads-table-container"
                   hx-indicator="#leads-loading"
                   hx-push-url="true"
                   title="Limpar todos os filtros">
                    Limpar
                </a>
            </div>
        </div>
    </form>
</section>

<!-- Indicador de Carregamento (Loading State) com suporte a prefers-reduced-motion -->
<div id="leads-loading" class="htmx-indicator" role="status" style="margin-bottom: 1rem;">
    <svg class="spinner-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 2a10 10 0 0 1 10 10"></path>
    </svg>
    <span>Atualizando resultados...</span>
</div>

<!-- Contêiner de Resultados / Tabela Paginada -->
<div id="leads-table-container">
    <?php require __DIR__ . '/partials/table.php'; ?>
</div>
