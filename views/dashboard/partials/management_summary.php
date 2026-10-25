<?php
$managementSummary = $managementSummary ?? null;
$managementErrors = $managementSummary['errors'] ?? ['summary' => ($managementSummaryError ?? null)];
$summaryStages = [
    'proposta' => ['label' => 'Proposta', 'key' => 'proposal'],
    'ganho' => ['label' => 'Fechados', 'key' => 'closed'],
    'perdido' => ['label' => 'Perdidos', 'key' => 'lost'],
];
$summaryOwnerName = static fn(array $owner): string => trim((string)($owner['name'] ?? '')) ?: 'Proprietário sem nome';
$summaryPropertyCode = static fn(array $property): string => trim((string)($property['code'] ?? '')) ?: 'Sem código';
$summaryPropertyTitle = static fn(array $property): string => trim((string)($property['title'] ?? '')) ?: 'Título não informado';
$summaryRetry = $summaryRetryUrl ?? '/#management-summary';
?>
<section class="management-summary" id="management-summary" aria-labelledby="management-summary-title">
    <header class="dashboard-section-heading">
        <div>
            <h2 id="management-summary-title">Resumo gerencial</h2>
            <p>Visão geral do histórico e dos vínculos atuais.</p>
        </div>
    </header>

    <div class="management-summary-grid">
        <section class="management-summary-domain" aria-labelledby="summary-leads-title">
            <header class="dashboard-section-heading">
                <h3 id="summary-leads-title">Leads</h3>
                <a href="/dashboard/leads">Ver dashboard</a>
            </header>
            <?php if (!empty($managementErrors['leads']) || !empty($managementErrors['summary'])): ?>
                <div class="dashboard-inline-error" role="alert">
                    <p>Não foi possível carregar os indicadores de Leads.</p>
                    <a href="<?= $e($summaryRetry) ?>" hx-get="/?partial=management-summary" hx-target="#management-summary" hx-swap="outerHTML" hx-indicator="#management-summary-loading" hx-push-url="false">Tentar novamente</a>
                </div>
            <?php else: ?>
                <ul class="dashboard-compact-metrics">
                    <?php foreach ($summaryStages as $stage => $item): ?>
                        <?php $value = $managementSummary['leads'][$item['key']] ?? 0; ?>
                        <li><a href="/dashboard/leads?<?= $e(http_build_query(['stage' => $stage])) ?>"><span><?= $e($item['label']) ?></span><strong><?= $e((string)$value) ?></strong></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="management-summary-domain" aria-labelledby="summary-properties-title">
            <header class="dashboard-section-heading">
                <h3 id="summary-properties-title">Imóveis</h3>
                <a href="/dashboard/properties">Ver dashboard</a>
            </header>
            <div class="management-summary-subgroups">
                <div>
                    <h4>Mais leads</h4>
                    <?php if (!empty($managementErrors['properties.top']) || !empty($managementErrors['summary'])): ?>
                        <div class="dashboard-inline-error" role="alert"><p>Ranking indisponível.</p><a href="<?= $e($summaryRetry) ?>" hx-get="/?partial=management-summary" hx-target="#management-summary" hx-swap="outerHTML" hx-indicator="#management-summary-loading" hx-push-url="false">Tentar novamente</a></div>
                    <?php elseif (empty($managementSummary['properties']['top'])): ?>
                        <p class="dashboard-empty">Ainda não há leads vinculados a imóveis.</p>
                    <?php else: ?>
                        <ol class="dashboard-ranking-list">
                            <?php foreach ($managementSummary['properties']['top'] as $property): ?>
                                <li><a href="/dashboard/properties?<?= $e(http_build_query(['property_id' => (int)$property['id']])) ?>"><span><strong><?= $e($summaryPropertyCode($property)) ?></strong><small><?= $e($summaryPropertyTitle($property)) ?></small></span><b><?= $e((string)$property['lead_count']) ?> leads</b></a></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>Tipos com leads</h4>
                    <?php if (!empty($managementErrors['properties.types']) || !empty($managementErrors['summary'])): ?>
                        <div class="dashboard-inline-error" role="alert"><p>Distribuição indisponível.</p><a href="<?= $e($summaryRetry) ?>" hx-get="/?partial=management-summary" hx-target="#management-summary" hx-swap="outerHTML" hx-indicator="#management-summary-loading" hx-push-url="false">Tentar novamente</a></div>
                    <?php elseif (empty($managementSummary['properties']['types'])): ?>
                        <p class="dashboard-empty">Nenhum tipo tem leads vinculados.</p>
                    <?php else: ?>
                        <ol class="dashboard-ranking-list">
                            <?php foreach ($managementSummary['properties']['types'] as $type): ?>
                                <li><a href="/dashboard/properties?<?= $e(http_build_query(['type' => $type['type']])) ?>"><span><?= $e($type['label']) ?></span><b><?= $e((string)$type['lead_count']) ?> leads</b></a></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="management-summary-domain management-summary-owners" aria-labelledby="summary-owners-title">
            <header class="dashboard-section-heading">
                <h3 id="summary-owners-title">Proprietários</h3>
                <a href="/dashboard/owners">Ver dashboard</a>
            </header>
            <?php if (!empty($managementErrors['owners.contact']) || !empty($managementErrors['summary'])): ?>
                <p class="dashboard-contact-metric">Último contato confirmado: <strong>indisponível</strong> <a href="<?= $e($summaryRetry) ?>" hx-get="/?partial=management-summary" hx-target="#management-summary" hx-swap="outerHTML" hx-indicator="#management-summary-loading" hx-push-url="false">Tentar novamente</a></p>
            <?php else: ?>
                <?php $contactDays = $managementSummary['owners']['days_since_last_confirmed_contact'] ?? null; ?>
                <p class="dashboard-contact-metric">Último contato confirmado: <strong><?= $contactDays === null ? '—' : $e((string)$contactDays . ' ' . ((int)$contactDays === 1 ? 'dia' : 'dias')) ?></strong></p>
            <?php endif; ?>
            <div class="owner-summary-rankings">
                <?php foreach (['count' => ['title' => 'Imóveis vinculados', 'ranking' => 'count'], 'sale' => ['title' => 'Soma de venda', 'ranking' => 'sale'], 'rent' => ['title' => 'Soma de aluguel', 'ranking' => 'rent']] as $key => $ranking): ?>
                    <div class="owner-summary-ranking">
                        <h4><?= $e($ranking['title']) ?></h4>
                        <?php if (!empty($managementErrors['owners.' . $key]) || !empty($managementErrors['summary'])): ?>
                            <div class="dashboard-inline-error" role="alert"><p>Ranking indisponível.</p><a href="<?= $e($summaryRetry) ?>" hx-get="/?partial=management-summary" hx-target="#management-summary" hx-swap="outerHTML" hx-indicator="#management-summary-loading" hx-push-url="false">Tentar novamente</a></div>
                        <?php elseif (empty($managementSummary['owners'][$key])): ?>
                            <p class="dashboard-empty">Sem resultados.</p>
                        <?php else: ?>
                            <ol class="dashboard-ranking-list">
                                <?php foreach ($managementSummary['owners'][$key] as $owner): ?>
                                    <?php $metric = $key === 'count' ? $e((string)$owner['metric_value']) . ' imóveis' : 'R$ ' . number_format((float)$owner['metric_value'], 2, ',', '.'); ?>
                                    <li><a href="/dashboard/owners?<?= $e(http_build_query(['owner_id' => (int)$owner['owner_id'], 'ranking' => $ranking['ranking']])) ?>"><span><?= $e($summaryOwnerName($owner)) ?></span><b><?= $metric ?></b></a></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
