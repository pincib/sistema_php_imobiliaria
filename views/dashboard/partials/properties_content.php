<?php
$dateFrom = $filters['date_from'] ?? '';
$dateTo = $filters['date_to'] ?? '';
$selectedType = $filters['type'] ?? '';
$selectedStatus = $filters['status'] ?? '';
$selectedStage = $filters['stage'] ?? '';
$selectedPropertyId = $filters['property_id'] ?? null;
$baseFilters = array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'type' => $selectedType, 'status' => $selectedStatus, 'stage' => $selectedStage]);
$propertiesUrl = static function (array $overrides = []) use ($baseFilters, $selectedPropertyId): string {
    $params = $baseFilters;
    if ($selectedPropertyId !== null) $params['property_id'] = $selectedPropertyId;
    $params = array_merge($params, $overrides);
    return '/dashboard/properties' . ($params ? '?' . http_build_query($params) : '');
};
$typeUrl = static function (string $type) use ($dateFrom, $dateTo, $selectedStatus, $selectedStage): string {
    $params = array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'status' => $selectedStatus, 'stage' => $selectedStage]);
    $params['type'] = $type;
    return '/dashboard/properties?' . http_build_query($params);
};
$propertyCode = static fn(array $property): string => trim((string)($property['code'] ?? ''));
$propertyTitle = static fn(array $property): string => trim((string)($property['title'] ?? '')) ?: 'Título não informado';
$propertyIdentifier = static fn(array $property): string => $propertyCode($property) !== '' ? $propertyCode($property) : $propertyTitle($property);
$leadName = static fn(array $lead): string => trim((string)($lead['name'] ?? '')) ?: 'Lead sem nome';
$hasPropertyFilters = $dateFrom !== '' || $dateTo !== '' || $selectedType !== '' || $selectedStatus !== '' || $selectedStage !== '';
?>
<section id="properties-dashboard-content" class="dashboard-content" aria-label="Indicadores de Imóveis">
    <?php if (!empty($errors)): ?>
        <div class="dashboard-filter-notices" role="status"><p>Alguns filtros foram ignorados:</p><ul><?php foreach ($errors as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form class="dashboard-filters" method="get" action="/dashboard/properties" hx-get="/dashboard/properties" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#properties-dashboard-loading">
        <fieldset class="dashboard-filter-group dashboard-filter-group--period">
            <legend>Período de criação do Lead</legend>
            <div class="dashboard-filter-fields">
                <label>Data inicial<input type="date" name="date_from" value="<?= $e($dateFrom) ?>"></label>
                <label>Data final<input type="date" name="date_to" value="<?= $e($dateTo) ?>"></label>
            </div>
        </fieldset>
        <fieldset class="dashboard-filter-group">
            <legend>Imóvel</legend>
            <div class="dashboard-filter-fields">
                <label>Tipo
                    <select name="type"><option value="">Todos os tipos</option><option value="unknown" <?= $selectedType === 'unknown' ? 'selected' : '' ?>>Tipo não identificado</option>
                        <?php foreach ($types as $key => $label): ?><option value="<?= $e($key) ?>" <?= $selectedType === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Situação
                    <select name="status"><option value="">Todas as situações</option><?php foreach ($statuses as $key => $label): ?><option value="<?= $e($key) ?>" <?= $selectedStatus === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
                </label>
            </div>
        </fieldset>
        <fieldset class="dashboard-filter-group">
            <legend>Lead</legend>
            <div class="dashboard-filter-fields">
                <label>Estágio atual
                    <select name="stage"><option value="">Todos os estágios</option><?php foreach ($stages as $key => $label): ?><option value="<?= $e($key) ?>" <?= $selectedStage === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
                </label>
            </div>
        </fieldset>
        <div class="dashboard-filter-actions"><button type="submit">Aplicar filtros</button><a href="/dashboard/properties" role="button" class="outline secondary">Limpar</a></div>
    </form>

    <div class="property-dashboard-grid">
        <section class="dashboard-panel" aria-labelledby="property-ranking-title">
            <header class="dashboard-section-heading"><div><h2 id="property-ranking-title">Imóveis com mais Leads</h2><p>Leads distintos em vínculos atuais, em qualquer tipo de relação.</p></div><span class="dashboard-period"><?= (int)$properties['total'] ?> imóvel(is)</span></header>
            <?php if (!empty($panel_errors['properties'])): ?>
                <div class="dashboard-inline-error" role="alert"><p>O ranking de imóveis não pôde ser carregado.</p><a href="<?= $e($propertiesUrl(['page' => 1])) ?>" hx-get="<?= $e($propertiesUrl(['page' => 1])) ?>" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-indicator="#properties-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
            <?php elseif (empty($properties['items'])): ?>
                <div class="dashboard-empty-state"><h3><?= $hasPropertyFilters ? 'Nenhum imóvel corresponde aos filtros' : 'Ainda não há vínculos entre imóveis e Leads' ?></h3><p><?= $hasPropertyFilters ? 'Ajuste ou limpe os filtros para ampliar a consulta.' : 'Associe Leads aos imóveis para começar a comparar o interesse.' ?></p><a href="<?= $hasPropertyFilters ? '/dashboard/properties' : '/properties' ?>" role="button" class="outline secondary"><?= $hasPropertyFilters ? 'Limpar filtros' : 'Abrir Imóveis' ?></a></div>
            <?php else: ?>
                <p class="dashboard-scroll-hint"><span class="dashboard-scroll-hint-tablet">Role horizontalmente para ver todas as colunas.</span><span class="dashboard-scroll-hint-mobile">Em telas estreitas, os dados aparecem em linhas empilhadas.</span></p>
                <div class="dashboard-table-scroll" role="region" aria-label="Ranking de imóveis por Leads" tabindex="0">
                    <table class="dashboard-table"><thead><tr><th scope="col">Imóvel</th><th scope="col">Tipo</th><th scope="col">Situação</th><th scope="col">Leads</th></tr></thead><tbody>
                        <?php foreach ($properties['items'] as $property): ?>
                            <tr>
                                <td data-label="Imóvel"><a href="/properties/<?= (int)$property['id'] ?>"><strong><?= $e($propertyIdentifier($property)) ?></strong><?php if ($propertyCode($property) !== ''): ?><small class="dashboard-table-secondary"><?= $e($propertyTitle($property)) ?></small><?php endif; ?></a></td>
                                <td data-label="Tipo"><?= $e($types[$property['type']] ?? 'Tipo não identificado') ?></td>
                                <td data-label="Situação"><?= $e($statuses[$property['status']] ?? $property['status']) ?></td>
                                <td data-label="Leads" class="dashboard-numeric"><a href="<?= $e($propertiesUrl(['property_id' => (int)$property['id'], 'page' => 1])) ?>"><?= $e((string)$property['lead_count']) ?> leads</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                </div>
                <?php if ($properties['pages'] > 1): ?><nav class="dashboard-pagination" aria-label="Paginação do ranking de imóveis"><span>Página <?= (int)$properties['page'] ?> de <?= (int)$properties['pages'] ?></span><div>
                    <?php if ($properties['page'] > 1): ?><a href="<?= $e($propertiesUrl(['page' => $properties['page'] - 1])) ?>" hx-get="<?= $e($propertiesUrl(['page' => $properties['page'] - 1])) ?>" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#properties-dashboard-loading">Anterior</a><?php endif; ?>
                    <?php if ($properties['page'] < $properties['pages']): ?><a href="<?= $e($propertiesUrl(['page' => $properties['page'] + 1])) ?>" hx-get="<?= $e($propertiesUrl(['page' => $properties['page'] + 1])) ?>" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#properties-dashboard-loading">Próxima</a><?php endif; ?>
                </div></nav><?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel" aria-labelledby="property-types-title">
            <header class="dashboard-section-heading"><div><h2 id="property-types-title">Leads por tipo de imóvel</h2><p>Um Lead pode aparecer em mais de um tipo.</p></div></header>
            <?php if (!empty($panel_errors['types'])): ?><div class="dashboard-inline-error" role="alert"><p>A comparação por tipo não pôde ser carregada.</p><a href="<?= $e($propertiesUrl()) ?>" hx-get="<?= $e($propertiesUrl()) ?>" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-indicator="#properties-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
            <?php elseif (empty($type_counts)): ?><p class="dashboard-empty">Não há tipos com Leads neste recorte.</p>
            <?php else: ?><ul class="dashboard-type-list"><?php foreach ($type_counts as $type): ?><li><a href="<?= $e($typeUrl($type['type'])) ?>"><span><?= $e($type['label']) ?></span><strong><?= $e((string)$type['lead_count']) ?> leads</strong></a></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
    </div>

    <?php if ($selectedPropertyId !== null): ?>
        <section class="dashboard-panel" aria-labelledby="selected-property-title">
            <?php if (!empty($panel_errors['selected_property']) || !empty($panel_errors['selected_property_leads'])): ?>
                <div class="dashboard-inline-error" role="alert"><p>Os Leads deste imóvel não puderam ser carregados.</p><a href="<?= $e($propertiesUrl()) ?>" hx-get="<?= $e($propertiesUrl()) ?>" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-indicator="#properties-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
            <?php elseif ($selected_property === null): ?>
                <div class="dashboard-empty-state"><h2 id="selected-property-title">Imóvel não encontrado</h2><p>O vínculo selecionado pode ter sido removido ou o imóvel não está disponível.</p><a href="/dashboard/properties" role="button" class="outline secondary">Voltar ao ranking</a></div>
            <?php else: ?>
                <header class="dashboard-section-heading"><div><h2 id="selected-property-title">Leads <?= $propertyCode($selected_property) !== '' ? 'de ' . $e($propertyCode($selected_property)) : 'do imóvel' ?></h2><p><a href="/properties/<?= (int)$selected_property['id'] ?>"><?= $e($propertyTitle($selected_property)) ?></a> · <?= (int)$selected_property_leads['total'] ?> Lead(s) no recorte atual.</p></div><a href="<?= $e($typeUrl($selectedType)) ?>" aria-label="Fechar detalhes deste imóvel">Fechar detalhe</a></header>
                <?php if (empty($selected_property_leads['items'])): ?><p class="dashboard-empty">Nenhum Lead deste imóvel corresponde aos filtros atuais.</p>
                <?php else: ?><ul class="dashboard-record-list"><?php foreach ($selected_property_leads['items'] as $lead): ?><li><a href="/leads/<?= (int)$lead['id'] ?>"><strong><?= $e($leadName($lead)) ?></strong><span class="badge <?= $e($getStageBadge($lead['stage'])) ?>"><?= $e($getStageLabel($lead['stage'])) ?></span><time datetime="<?= $e($lead['created_at']) ?>"><?= $e($formatDate($lead['created_at_local'], 'd/m/Y')) ?></time></a></li><?php endforeach; ?></ul>
                    <?php if ($selected_property_leads['pages'] > 1): ?><nav class="dashboard-pagination" aria-label="Paginação dos Leads do imóvel"><span>Página <?= (int)$selected_property_leads['page'] ?> de <?= (int)$selected_property_leads['pages'] ?></span><div>
                        <?php if ($selected_property_leads['page'] > 1): ?><a href="<?= $e($propertiesUrl(['lead_page' => $selected_property_leads['page'] - 1])) ?>" hx-get="<?= $e($propertiesUrl(['lead_page' => $selected_property_leads['page'] - 1])) ?>" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#properties-dashboard-loading">Anterior</a><?php endif; ?>
                        <?php if ($selected_property_leads['page'] < $selected_property_leads['pages']): ?><a href="<?= $e($propertiesUrl(['lead_page' => $selected_property_leads['page'] + 1])) ?>" hx-get="<?= $e($propertiesUrl(['lead_page' => $selected_property_leads['page'] + 1])) ?>" hx-target="#properties-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#properties-dashboard-loading">Próxima</a><?php endif; ?>
                    </div></nav><?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
