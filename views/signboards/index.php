<header class="properties-page-header">
    <div>
        <h1>Placas</h1>
        <p>Acompanhe instalações, validades e retiradas vinculadas aos imóveis.</p>
    </div>
    <a href="/signboards/new" role="button">Cadastrar placa</a>
</header>

<section class="signboard-summary" aria-label="Resumo operacional">
    <?php foreach ($statuses as $key => $label): ?>
        <a class="signboard-summary-item" href="/signboards?status=<?= $e($key) ?>"><strong><?= $e((string)($overview['statuses'][$key] ?? 0)) ?></strong><span><?= $e($label) ?></span></a>
    <?php endforeach ?>
</section>

<form class="properties-filters signboard-filters" method="GET" action="/signboards">
    <label>Buscar placa, imóvel ou responsável
        <input type="search" name="q" maxlength="120" value="<?= $e($filters['q']) ?>" placeholder="Localização, endereço, bairro, cidade, código ou responsável">
    </label>
    <label>Status
        <select name="status"><option value="">Todos</option><?php foreach ($statuses as $key=>$label): ?><option value="<?= $e($key) ?>" <?= $filters['status']===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach ?></select>
    </label>
    <button type="submit">Buscar placas</button>
    <?php if ($filters['q']!=='' || $filters['status']!==''): ?><a href="/signboards" role="button" class="secondary outline">Limpar</a><?php endif ?>
</form>

<p class="properties-results-count"><?= $e((string)$total) ?> placa(s)<?= $totalPages>1?' · Página '.$e((string)$page).' de '.$e((string)$totalPages):'' ?></p>
<p class="properties-scroll-hint signboard-scroll-hint" id="signboards-table-hint">Em telas estreitas, deslize a tabela para consultar todas as informações.</p>
<figure class="properties-table-wrap" role="region" aria-label="Placas cadastradas" aria-describedby="signboards-table-hint" tabindex="0">
    <table class="properties-table signboards-table">
        <thead><tr><th scope="col">Situação</th><th scope="col">Imóvel</th><th scope="col">Ponto</th><th scope="col">Responsável</th><th scope="col">Instalação</th><th scope="col">Validade</th></tr></thead>
        <tbody>
            <?php if (!$signboards): ?><tr><td colspan="6" class="properties-empty-state"><?= $filters['q']!=='' || $filters['status']!==''?'Nenhuma placa corresponde à busca.':'Nenhuma placa cadastrada. Cadastre a primeira para começar a acompanhar as movimentações.' ?></td></tr><?php endif ?>
            <?php foreach ($signboards as $row): ?><tr>
                <td><span class="signboard-status signboard-status-<?= $e($row['operational_status']) ?>"><?= $e($statuses[$row['operational_status']] ?? $row['operational_status']) ?></span></td>
                <td><a href="/signboards/<?= $e((string)$row['id']) ?>"><strong><?= $e($row['property_code']) ?></strong><br><?= $e($row['property_title']) ?></a><br><small><?= $e(implode(', ',array_filter([$row['neighborhood'],$row['city']]))) ?></small></td>
                <td><?= $e($row['location']) ?></td>
                <td><?= $e($row['responsible_name']) ?></td>
                <td><?= $row['installed_at']?$e(substr($row['installed_at'],0,10)):($row['removed_at']?'—':'Aguardando instalação') ?></td>
                <td><?= $row['expires_at']?$e(substr($row['expires_at'],0,10)):'Sem validade' ?></td>
            </tr><?php endforeach ?>
        </tbody>
    </table>
</figure>

<?php if ($totalPages>1): ?><nav class="properties-pagination" aria-label="Paginação de placas"><ul>
    <?php if($page>1): ?><li><a href="?<?= $e(http_build_query(array_merge($filters,['page'=>$page-1]))) ?>">Anterior</a></li><?php endif ?>
    <li><span aria-current="page">Página <?= $e((string)$page) ?> de <?= $e((string)$totalPages) ?></span></li>
    <?php if($page<$totalPages): ?><li><a href="?<?= $e(http_build_query(array_merge($filters,['page'=>$page+1]))) ?>">Próxima</a></li><?php endif ?>
</ul></nav><?php endif ?>
