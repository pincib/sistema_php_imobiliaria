<?php
/** @var list<array<string,mixed>> $properties */
?>
<header class="properties-page-header">
    <div>
        <h1>Imóveis</h1>
        <p>Consulte o inventário e veja quais oportunidades estão relacionadas.</p>
    </div>
    <div class="properties-page-actions">
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <a href="/leads/import?dataset=properties&amp;return_to=properties" role="button" class="outline secondary">Importar CSV</a>
        <?php endif ?>
        <a href="/properties/new" role="button">Cadastrar imóvel</a>
    </div>
</header>

<form class="properties-filters" method="GET" action="/properties">
    <label>Busca contextual
        <input type="search" name="q" value="<?= $e($filters['q']) ?>" placeholder="Código, endereço, bairro, cidade ou lead">
    </label>
    <label>Status
        <select name="status"><option value="">Todos</option><?php foreach ($statuses as $key => $label): ?><option value="<?= $e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach ?></select>
    </label>
    <label>Tipo
        <select name="type"><option value="">Todos</option><?php foreach ($types as $key => $label): ?><option value="<?= $e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach ?></select>
    </label>
    <label>Cidade
        <select name="city"><option value="">Todas</option><?php foreach ($cities as $city): ?><option value="<?= $e($city) ?>" <?= $filters['city'] === $city ? 'selected' : '' ?>><?= $e($city) ?></option><?php endforeach ?></select>
    </label>
    <button type="submit">Buscar imóveis</button>
</form>

<p class="properties-results-count">
    <?= $e((string)$total) ?> imóvel(is)<?= $totalPages > 1 ? ' · Página ' . $e((string)$page) . ' de ' . $e((string)$totalPages) : '' ?>
</p>
<p class="properties-scroll-hint" id="properties-table-hint">Em telas estreitas, os imóveis são apresentados em cartões com os dados identificados.</p>
<figure class="properties-table-wrap" role="region" aria-label="Resultados de imóveis" aria-describedby="properties-table-hint" tabindex="0">
    <table class="properties-table property-records-table">
        <thead><tr><th scope="col">Código</th><th scope="col">Imóvel</th><th scope="col">Localização</th><th scope="col">Preço</th><th scope="col">Status</th><th scope="col">Leads</th><th scope="col">Ações</th></tr></thead>
        <tbody>
            <?php if (!$properties): ?>
                <tr><td colspan="7" class="properties-empty-state"><?= $filters['q'] || $filters['status'] || $filters['type'] || $filters['city'] ? 'Nenhum imóvel corresponde aos filtros.' : 'Nenhum imóvel cadastrado. Comece pelo cadastro do primeiro imóvel.' ?></td></tr>
            <?php endif ?>
            <?php foreach ($properties as $p): ?>
                <tr>
                    <td data-label="Código"><strong><?= $e($p['code']) ?></strong></td>
                    <td data-label="Imóvel"><a href="/properties/<?= $e((string)$p['id']) ?>"><?= $e($p['title']) ?></a><br><small><?= $e($types[$p['type']] ?? $p['type']) ?></small></td>
                    <td data-label="Localização"><?= $e(implode(', ', array_filter([$p['neighborhood'], $p['city']]))) ?: '—' ?></td>
                    <td data-label="Preço"><?php if (($p['intention'] ?? '') === 'venda_locacao'): ?>Venda: R$ <?= number_format((float)$p['sale_price'], 2, ',', '.') ?><br>Locação: R$ <?= number_format((float)$p['rent_price'], 2, ',', '.') ?><?php elseif (($p['intention'] ?? '') === 'locacao'): ?><?= $p['rent_price'] === null ? '—' : 'R$ ' . number_format((float)$p['rent_price'], 2, ',', '.') ?> / mês<?php else: ?><?= $p['sale_price'] === null && $p['price'] === null ? '—' : 'R$ ' . number_format((float)($p['sale_price'] ?? $p['price']), 2, ',', '.') ?><?php endif ?></td>
                    <td data-label="Status"><?= $e($statuses[$p['status']] ?? $p['status']) ?></td>
                    <td data-label="Leads"><?= $e((string)$p['lead_count']) ?></td>
                    <td data-label="Ações"><a class="property-edit-link" href="/properties/<?= $e((string)$p['id']) ?>/edit">Editar</a></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</figure>

<?php if ($totalPages > 1): ?>
    <nav class="properties-pagination" aria-label="Paginação de imóveis">
        <ul>
            <?php if ($page > 1): ?><li><a href="?<?= $e(http_build_query(array_merge($filters, ['page' => $page - 1]))) ?>">Anterior</a></li><?php endif ?>
            <li><span aria-current="page">Página <?= $e((string)$page) ?> de <?= $e((string)$totalPages) ?></span></li>
            <?php if ($page < $totalPages): ?><li><a href="?<?= $e(http_build_query(array_merge($filters, ['page' => $page + 1]))) ?>">Próxima</a></li><?php endif ?>
        </ul>
    </nav>
<?php endif ?>
