<header class="properties-page-header">
    <div>
        <h1>Placas</h1>
        <p>Acompanhe a compra, preparação e uso das placas vinculadas aos imóveis.</p>
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
        <input type="search" name="q" maxlength="120" value="<?= $e($filters['q']) ?>" placeholder="Código, nome ou endereço do imóvel, responsável">
    </label>
    <label>Situação operacional
        <select name="status"><option value="">Todas</option><?php foreach ($statuses as $key=>$label): ?><option value="<?= $e($key) ?>" <?= $filters['status']===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach ?></select>
    </label>
    <button type="submit">Buscar placas</button>
    <?php if ($filters['q']!=='' || $filters['status']!==''): ?><a href="/signboards" role="button" class="secondary outline">Limpar</a><?php endif ?>
</form>

<p class="properties-results-count"><?= $e((string)count($signboards)) ?> placa(s) no quadro<?= $filters['status']!==''?' · filtro: '.$e($statuses[$filters['status']]):'' ?></p>
<p class="signboard-board-hint">Mova cada placa pela lista de etapas ao final do cartão. A movimentação fica registrada no histórico da placa.</p>
<section class="signboard-board" aria-label="Quadro de etapas das placas">
    <?php foreach ($stages as $stageKey=>$stageLabel): ?>
        <section class="signboard-lane" aria-labelledby="lane-<?= $e($stageKey) ?>">
            <header class="signboard-lane-header"><h2 id="lane-<?= $e($stageKey) ?>"><?= $e($stageLabel) ?></h2><span><?= $e((string)($stageCounts[$stageKey] ?? 0)) ?></span></header>
            <div class="signboard-lane-cards">
                <?php $laneItems = array_filter($signboards, static fn(array $row): bool => $row['board_stage'] === $stageKey); ?>
                <?php if (!$laneItems): ?><p class="signboard-lane-empty"><?= $filters['q']!=='' || $filters['status']!==''?'Nenhuma placa nesta etapa para os filtros atuais.':'Nenhuma placa nesta etapa.' ?></p><?php endif ?>
                <?php foreach ($laneItems as $row): ?>
                    <article class="signboard-board-card">
                        <header><a href="/signboards/<?= $e((string)$row['id']) ?>"><strong><?= $row['property_id'] !== null ? $e($row['property_code'].' · '.$row['property_title']) : 'Placa sem imóvel · #'.$e((string)$row['id']) ?></strong></a><span class="signboard-status signboard-status-<?= $e($row['operational_status']) ?>"><?= $e($statuses[$row['operational_status']] ?? $row['operational_status']) ?></span></header>
                        <dl>
                            <div><dt>Material</dt><dd><?= $e($row['material'] === 'lona' ? 'Lona' : ($row['material'] === 'adesivo' ? 'Adesivo' : 'Outro')) ?></dd></div>
                            <div><dt>Tamanho</dt><dd><?= $row['height_cm'] !== null ? $e(rtrim(rtrim(number_format((float)$row['height_cm'],2,',','.'),'0'),',')) : '—' ?> × <?= $row['length_cm'] !== null ? $e(rtrim(rtrim(number_format((float)$row['length_cm'],2,',','.'),'0'),',')) : '—' ?> cm</dd></div>
                            <div><dt>Modalidade</dt><dd><?= $e(['compra'=>'Compra','aluguel'=>'Aluguel','compra_aluguel'=>'Compra e aluguel'][$row['deal_type']] ?? $row['deal_type']) ?></dd></div>
                            <div><dt>Responsável</dt><dd><?= $e($row['responsible_name']) ?></dd></div>
                        </dl>
                        <?php if ($stageKey === 'disponivel' && $row['property_id'] === null): ?><a href="/signboards/<?= $e((string)$row['id']) ?>/assign" role="button">Colocar placa</a><?php endif ?>
                        <form class="signboard-stage-form" method="POST" action="/signboards/<?= $e((string)$row['id']) ?>/stage">
                            <?= $csrfField ?>
                            <label for="stage-<?= $e((string)$row['id']) ?>">Mover para
                                <select id="stage-<?= $e((string)$row['id']) ?>" name="board_stage" aria-label="Mover placa <?= $row['property_id'] !== null ? $e($row['property_code']) : '#'.$e((string)$row['id']) ?> para etapa">
                                    <?php foreach ($stages as $optionKey=>$optionLabel): ?><option value="<?= $e($optionKey) ?>" <?= $stageKey===$optionKey?'selected':'' ?> <?= $optionKey==='usadas' && $row['property_id']===null?'disabled':'' ?>><?= $e($optionLabel) ?></option><?php endforeach ?>
                                </select>
                            </label>
                            <button type="submit" class="secondary outline">Mover</button>
                        </form>
                        <form class="signboard-delete-form" method="POST" action="/signboards/<?= $e((string)$row['id']) ?>/delete" onsubmit="return confirm('Excluir esta placa do quadro? O histórico será mantido para auditoria.');">
                            <?= $csrfField ?>
                            <button type="submit" class="btn-danger">Excluir placa</button>
                        </form>
                    </article>
                <?php endforeach ?>
            </div>
        </section>
    <?php endforeach ?>
</section>
