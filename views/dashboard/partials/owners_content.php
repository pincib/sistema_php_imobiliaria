<?php
$baseFilters = array_filter($filters, static fn($value) => $value !== null && $value !== '');
$pageParams = [
    'count_page' => $rankings['count']['page'] ?? 1,
    'sale_page' => $rankings['sale']['page'] ?? 1,
    'rent_page' => $rankings['rent']['page'] ?? 1,
];
$ownersUrl = static function (array $overrides = []) use ($baseFilters, $pageParams): string {
    $params = array_merge($baseFilters, $pageParams, $overrides);
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    return '/dashboard/owners' . ($params ? '?' . http_build_query($params) : '');
};
$money = static fn($value): string => 'R$ ' . number_format((float)$value, 2, ',', '.');
$ownerName = static fn(array $owner): string => trim((string)($owner['name'] ?? '')) ?: 'Proprietário sem nome';
$propertyCode = static fn(array $property): string => trim((string)($property['code'] ?? ''));
$propertyTitle = static fn(array $property): string => trim((string)($property['title'] ?? '')) ?: 'Título não informado';
$valueMin = $filters['value_min'] === null ? '' : number_format((float)$filters['value_min'], 2, '.', '');
$valueMax = $filters['value_max'] === null ? '' : number_format((float)$filters['value_max'], 2, '.', '');
$hasFilterValue = static fn(string $key): bool => ($filters[$key] ?? null) !== null && ($filters[$key] ?? null) !== '';
$countFiltersActive = $hasFilterValue('type') || $hasFilterValue('status') || $hasFilterValue('intention');
$valueFiltersActive = $hasFilterValue('type') || $hasFilterValue('status') || $hasFilterValue('value_min') || $hasFilterValue('value_max');
$daysSinceContact = $days_since_last_confirmed_contact === null ? '—' : (int)$days_since_last_confirmed_contact . ' ' . ((int)$days_since_last_confirmed_contact === 1 ? 'dia' : 'dias');
?>
<section id="owners-dashboard-content" class="dashboard-content" aria-label="Rankings de Proprietários">
    <?php if (!empty($errors)): ?><div class="dashboard-filter-notices" role="status"><p>Alguns filtros foram ignorados:</p><ul><?php foreach ($errors as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="owner-contact-summary" aria-labelledby="owner-contact-title">
        <?php if (!empty($panel_errors['contact'])): ?><div class="dashboard-inline-error" role="alert"><p>O indicador de contato não pôde ser carregado.</p><a href="<?= $e($ownersUrl()) ?>" hx-get="<?= $e($ownersUrl()) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-indicator="#owners-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
        <?php else: ?><p id="owner-contact-title">Dias desde o último contato confirmado com proprietário <strong><?= $e($daysSinceContact) ?></strong></p><small>Conta o registro confirmado mais recente no sistema e não identifica o proprietário.</small><?php endif; ?>
    </section>

    <form class="dashboard-filters owner-dashboard-filters" method="get" action="/dashboard/owners" hx-get="/dashboard/owners" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#owners-dashboard-loading">
        <fieldset class="dashboard-filter-group">
            <legend>Imóvel</legend>
            <div class="dashboard-filter-fields">
                <label>Tipo<select name="type"><option value="">Todos os tipos</option><?php foreach ($types as $key => $label): ?><option value="<?= $e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
                <label>Situação<select name="status"><option value="">Todas as situações</option><?php foreach ($statuses as $key => $label): ?><option value="<?= $e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
            </div>
        </fieldset>
        <fieldset class="dashboard-filter-group">
            <legend>Ranking por quantidade</legend>
            <div class="dashboard-filter-fields">
                <label>Modalidade<select name="intention"><option value="">Todas as modalidades</option><?php foreach ($intentions as $key => $label): ?><option value="<?= $e($key) ?>" <?= $filters['intention'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
            </div>
        </fieldset>
        <fieldset class="dashboard-filter-group dashboard-filter-group--values">
            <legend>Rankings por valor</legend>
            <div class="dashboard-filter-fields">
                <label>Valor mínimo por proprietário<input type="number" name="value_min" min="0" step="0.01" value="<?= $e($valueMin) ?>" inputmode="decimal"></label>
                <label>Valor máximo por proprietário<input type="number" name="value_max" min="0" step="0.01" value="<?= $e($valueMax) ?>" inputmode="decimal"></label>
            </div>
        </fieldset>
        <div class="dashboard-filter-actions"><button type="submit">Aplicar filtros</button><a href="/dashboard/owners" role="button" class="outline secondary">Limpar</a></div>
        <p class="dashboard-filter-help">Tipo e situação afetam todos os rankings. Modalidade afeta apenas a quantidade. Cada limite de valor filtra separadamente a soma de venda e a soma de aluguel por proprietário.</p>
    </form>

    <section class="dashboard-panel" aria-labelledby="owner-count-title">
        <header class="dashboard-section-heading"><div><h2 id="owner-count-title">Mais imóveis vinculados</h2><p>Imóveis distintos atualmente vinculados; todos os status entram por padrão.</p></div><span class="dashboard-period"><?= (int)$rankings['count']['total'] ?> proprietário(s)</span></header>
        <?php if (!empty($panel_errors['count'])): ?><div class="dashboard-inline-error" role="alert"><p>O ranking por quantidade não pôde ser carregado.</p><a href="<?= $e($ownersUrl(['count_page' => 1])) ?>" hx-get="<?= $e($ownersUrl(['count_page' => 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-indicator="#owners-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
        <?php elseif (empty($rankings['count']['items'])): ?><div class="dashboard-empty-state"><h3><?= $countFiltersActive ? 'Nenhum proprietário corresponde aos filtros de quantidade' : 'Ainda não há proprietários com imóveis vinculados' ?></h3><p><?= $countFiltersActive ? 'Ajuste ou limpe os filtros de imóvel e modalidade para ampliar o ranking.' : 'Proprietários sem imóveis atuais não entram neste ranking.' ?></p><a href="<?= $countFiltersActive ? '/dashboard/owners' : '/owners' ?>" role="button" class="outline secondary"><?= $countFiltersActive ? 'Limpar filtros' : 'Abrir Proprietários' ?></a></div>
        <?php else: ?>
            <div class="dashboard-table-scroll" role="region" aria-label="Ranking de proprietários por quantidade de imóveis" tabindex="0"><table class="dashboard-table"><thead><tr><th scope="col">Proprietário</th><th scope="col">Imóveis</th></tr></thead><tbody>
                <?php foreach ($rankings['count']['items'] as $owner): ?><tr><td data-label="Proprietário"><a href="/owners/<?= (int)$owner['owner_id'] ?>"><?= $e($ownerName($owner)) ?></a></td><td data-label="Imóveis" class="dashboard-numeric"><a href="<?= $e($ownersUrl(['owner_id' => (int)$owner['owner_id'], 'ranking' => 'count'])) ?>"><?= (int)$owner['metric_value'] ?> imóveis</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ($rankings['count']['pages'] > 1): ?><nav class="dashboard-pagination" aria-label="Paginação do ranking por quantidade"><span>Página <?= (int)$rankings['count']['page'] ?> de <?= (int)$rankings['count']['pages'] ?></span><div><?php if ($rankings['count']['page'] > 1): ?><a href="<?= $e($ownersUrl(['count_page' => $rankings['count']['page'] - 1])) ?>" hx-get="<?= $e($ownersUrl(['count_page' => $rankings['count']['page'] - 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#owners-dashboard-loading">Anterior</a><?php endif; ?><?php if ($rankings['count']['page'] < $rankings['count']['pages']): ?><a href="<?= $e($ownersUrl(['count_page' => $rankings['count']['page'] + 1])) ?>" hx-get="<?= $e($ownersUrl(['count_page' => $rankings['count']['page'] + 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#owners-dashboard-loading">Próxima</a><?php endif; ?></div></nav><?php endif; ?>
        <?php endif; ?>
    </section>

    <div class="owner-value-grid">
        <?php foreach (['sale' => ['title' => 'Maior soma de venda'], 'rent' => ['title' => 'Maior soma de aluguel']] as $kind => $panel): ?>
            <section class="dashboard-panel" aria-labelledby="owner-<?= $e($kind) ?>-title">
                <header class="dashboard-section-heading"><div><h2 id="owner-<?= $e($kind) ?>-title"><?= $e($panel['title']) ?></h2><p>Soma dos preços cadastrados em imóveis elegíveis para <?= $kind === 'sale' ? 'venda' : 'aluguel' ?>.</p></div><span class="dashboard-period"><?= (int)$rankings[$kind]['total'] ?> proprietário(s)</span></header>
                <?php if (!empty($panel_errors[$kind])): ?><div class="dashboard-inline-error" role="alert"><p>O ranking de <?= $kind === 'sale' ? 'venda' : 'aluguel' ?> não pôde ser carregado.</p><a href="<?= $e($ownersUrl([$kind . '_page' => 1])) ?>" hx-get="<?= $e($ownersUrl([$kind . '_page' => 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-indicator="#owners-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
                <?php elseif (empty($rankings[$kind]['items'])): ?><div class="dashboard-empty-state"><h3><?= $valueFiltersActive ? 'Nenhum proprietário corresponde aos filtros de ' . ($kind === 'sale' ? 'venda' : 'aluguel') : 'Ainda não há valores elegíveis para ' . ($kind === 'sale' ? 'venda' : 'aluguel') ?></h3><p><?= $valueFiltersActive ? 'Ajuste ou limpe os filtros de imóvel e valor para ampliar este ranking.' : 'O ranking considera preços cadastrados em imóveis atualmente vinculados e elegíveis para esta modalidade.' ?></p><?php if ($valueFiltersActive): ?><a href="/dashboard/owners" role="button" class="outline secondary">Limpar filtros</a><?php endif; ?></div>
                <?php else: ?>
                    <div class="dashboard-table-scroll" role="region" aria-label="<?= $e($panel['title']) ?>" tabindex="0"><table class="dashboard-table"><thead><tr><th scope="col">Proprietário</th><th scope="col">Total (R$)</th></tr></thead><tbody>
                        <?php foreach ($rankings[$kind]['items'] as $owner): ?><tr><td data-label="Proprietário"><a href="/owners/<?= (int)$owner['owner_id'] ?>"><?= $e($ownerName($owner)) ?></a></td><td data-label="Total (R$)" class="dashboard-numeric"><a href="<?= $e($ownersUrl(['owner_id' => (int)$owner['owner_id'], 'ranking' => $kind])) ?>"><?= $e($money($owner['metric_value'])) ?></a></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                    <?php if ($rankings[$kind]['pages'] > 1): ?><nav class="dashboard-pagination" aria-label="Paginação do ranking de <?= $e($kind === 'sale' ? 'venda' : 'aluguel') ?>"><span>Página <?= (int)$rankings[$kind]['page'] ?> de <?= (int)$rankings[$kind]['pages'] ?></span><div><?php if ($rankings[$kind]['page'] > 1): ?><a href="<?= $e($ownersUrl([$kind . '_page' => $rankings[$kind]['page'] - 1])) ?>" hx-get="<?= $e($ownersUrl([$kind . '_page' => $rankings[$kind]['page'] - 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#owners-dashboard-loading">Anterior</a><?php endif; ?><?php if ($rankings[$kind]['page'] < $rankings[$kind]['pages']): ?><a href="<?= $e($ownersUrl([$kind . '_page' => $rankings[$kind]['page'] + 1])) ?>" hx-get="<?= $e($ownersUrl([$kind . '_page' => $rankings[$kind]['page'] + 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#owners-dashboard-loading">Próxima</a><?php endif; ?></div></nav><?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if ($selected_owner !== null): ?>
        <section class="dashboard-panel" aria-labelledby="owner-contribution-title">
            <header class="dashboard-section-heading"><div><h2 id="owner-contribution-title">Imóveis considerados — <?= $e(trim((string)($selected_owner['name'] ?? '')) ?: 'Proprietário sem nome') ?></h2><p><?= ['count' => 'Quantidade de imóveis', 'sale' => 'Soma de venda', 'rent' => 'Soma de aluguel'][$selected_owner['ranking']] ?? '' ?>. A ficha do proprietário pode incluir vínculos fora deste recorte.</p></div><a href="<?= $e($ownersUrl(['owner_id' => '', 'ranking' => ''])) ?>">Fechar detalhe</a></header>
            <?php if (!empty($panel_errors['contributions'])): ?><div class="dashboard-inline-error" role="alert"><p>Os imóveis que formam este resultado não puderam ser carregados.</p><a href="<?= $e($ownersUrl()) ?>" hx-get="<?= $e($ownersUrl()) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-indicator="#owners-dashboard-loading" hx-push-url="false">Tentar novamente</a></div>
            <?php elseif ($selected_owner['name'] === null): ?><div class="dashboard-empty-state"><h3>Proprietário não encontrado</h3><p>O cadastro pode ter sido removido.</p><a href="/dashboard/owners" role="button" class="outline secondary">Voltar aos rankings</a></div>
            <?php elseif (empty($contributions['items'])): ?><p class="dashboard-empty">Nenhum imóvel elegível corresponde ao ranking e aos filtros atuais.</p>
            <?php else: ?><div class="dashboard-table-scroll" role="region" aria-label="Imóveis que contribuem para o ranking" tabindex="0"><table class="dashboard-table"><thead><tr><th scope="col">Imóvel</th><th scope="col">Tipo</th><th scope="col">Situação</th><th scope="col"><?= $selected_owner['ranking'] === 'count' ? 'Contribuição' : 'Valor considerado' ?></th></tr></thead><tbody>
                <?php foreach ($contributions['items'] as $property): ?><tr><td data-label="Imóvel"><a href="/properties/<?= (int)$property['property_id'] ?>"><strong><?= $e($propertyCode($property) !== '' ? $propertyCode($property) : $propertyTitle($property)) ?></strong><?php if ($propertyCode($property) !== ''): ?><small class="dashboard-table-secondary"><?= $e($propertyTitle($property)) ?></small><?php endif; ?></a></td><td data-label="Tipo"><?= $e($types[$property['type']] ?? 'Tipo não identificada') ?></td><td data-label="Situação"><?= $e($statuses[$property['status']] ?? $property['status']) ?></td><td data-label="<?= $selected_owner['ranking'] === 'count' ? 'Contribuição' : 'Valor considerado' ?>" class="dashboard-numeric"><?= $selected_owner['ranking'] === 'count' ? '1 imóvel' : $e($money($property['contribution_value'])) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
                <?php if ($contributions['pages'] > 1): ?><nav class="dashboard-pagination" aria-label="Paginação dos imóveis considerados"><span>Página <?= (int)$contributions['page'] ?> de <?= (int)$contributions['pages'] ?></span><div><?php if ($contributions['page'] > 1): ?><a href="<?= $e($ownersUrl(['owner_id' => $selected_owner['owner_id'], 'ranking' => $selected_owner['ranking'], 'contribution_page' => $contributions['page'] - 1])) ?>" hx-get="<?= $e($ownersUrl(['owner_id' => $selected_owner['owner_id'], 'ranking' => $selected_owner['ranking'], 'contribution_page' => $contributions['page'] - 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#owners-dashboard-loading">Anterior</a><?php endif; ?><?php if ($contributions['page'] < $contributions['pages']): ?><a href="<?= $e($ownersUrl(['owner_id' => $selected_owner['owner_id'], 'ranking' => $selected_owner['ranking'], 'contribution_page' => $contributions['page'] + 1])) ?>" hx-get="<?= $e($ownersUrl(['owner_id' => $selected_owner['owner_id'], 'ranking' => $selected_owner['ranking'], 'contribution_page' => $contributions['page'] + 1])) ?>" hx-target="#owners-dashboard-content" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#owners-dashboard-loading">Próxima</a><?php endif; ?></div></nav><?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
