<?php
/** @var array<string, string> $filters */
/** @var list<string> $errors */
/** @var array<string, string> $stages */
/** @var list<array<string, mixed>> $brokers */
/** @var array<string, mixed> $report */
?>
<header class="reports-header">
    <div>
        <h1>Relatórios gerenciais</h1>
        <p>Indicadores conferíveis nos leads e no histórico de interações da operação.</p>
    </div>
    <a href="/leads" role="button" class="outline secondary">Abrir leads</a>
</header>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-warning" role="alert">
        <?= $e($error) ?> O relatório abaixo usa o período de <?= $e(date('d/m/Y', strtotime($filters['from']))) ?> a <?= $e(date('d/m/Y', strtotime($filters['to']))) ?> e os filtros válidos exibidos no formulário.
    </div>
<?php endforeach; ?>

<form class="reports-filters" method="GET" action="/reports">
    <div>
        <label for="report-from">Data inicial</label>
        <input id="report-from" type="date" name="from" value="<?= $e($filters['from']) ?>" required>
    </div>
    <div>
        <label for="report-to">Data final</label>
        <input id="report-to" type="date" name="to" value="<?= $e($filters['to']) ?>" required>
    </div>
    <div>
        <label for="report-stage">Estágio atual</label>
        <select id="report-stage" name="stage">
            <option value="">Todos os estágios</option>
            <?php foreach ($stages as $key => $label): ?>
                <option value="<?= $e($key) ?>" <?= $filters['stage'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="report-broker">Corretor responsável atual</label>
        <select id="report-broker" name="user_id">
            <option value="">Todos os corretores</option>
            <option value="unassigned" <?= $filters['user_id'] === 'unassigned' ? 'selected' : '' ?>>Não atribuído</option>
            <?php foreach ($brokers as $broker): ?>
                <option value="<?= $e((string)$broker['id']) ?>" <?= $filters['user_id'] === (string)$broker['id'] ? 'selected' : '' ?>><?= $e($broker['name'] . ((int)$broker['is_active'] === 1 ? '' : ' (inativo)')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="reports-filter-actions">
        <button type="submit" class="primary">Aplicar filtros</button>
        <a href="/reports" role="button" class="outline secondary">Limpar</a>
    </div>
</form>

<section aria-labelledby="period-metrics-heading" class="reports-section">
    <div class="reports-section-heading">
        <div>
            <h2 id="period-metrics-heading">Atividade no período</h2>
            <p><?= $e(date('d/m/Y', strtotime($filters['from']))) ?> a <?= $e(date('d/m/Y', strtotime($filters['to']))) ?> · filtros de estágio e corretor usam os valores atuais do lead.</p>
        </div>
        <form method="GET" action="/reports/export.csv">
            <?php foreach ($filters as $name => $value): ?>
                <input type="hidden" name="<?= $e($name) ?>" value="<?= $e($value) ?>">
            <?php endforeach; ?>
            <label for="report-export-dataset">Exportar</label>
            <select id="report-export-dataset" name="dataset" aria-label="Conjunto de dados para exportar">
                <option value="leads">Leads criados no período</option>
                <option value="interactions">Interações no período</option>
                <option value="portfolio">Carteira atual (sem filtro de data)</option>
            </select>
            <button type="submit" class="outline secondary">Exportar CSV</button>
            <small class="reports-export-limit">Até <?= $e((string)\App\Modules\Reports\Services\ReportsService::MAX_EXPORT_ROWS) ?> registros por arquivo. Consultas maiores não geram arquivo; refine os filtros.</small>
        </form>
    </div>

    <dl class="reports-metrics">
        <div>
            <dt>Leads criados</dt>
            <dd><?= $e((string)$report['created_total']) ?></dd>
            <p>Contagem de registros criados nas datas selecionadas. <a class="reports-detail-link" href="#source-records-heading">Ver até <?= $e((string)\App\Modules\Reports\Services\ReportsService::DETAIL_ROWS) ?> leads de origem</a>.</p>
        </div>
        <div>
            <dt>Interações realizadas</dt>
            <dd><?= $e((string)$report['interactions_total']) ?></dd>
            <p>Contagem do histórico de contatos no período, associada aos leads que atendem aos filtros atuais. <a class="reports-detail-link" href="#interaction-records-heading">Ver até <?= $e((string)\App\Modules\Reports\Services\ReportsService::DETAIL_ROWS) ?> interações</a>.</p>
        </div>
    </dl>
</section>

<section class="reports-section" aria-labelledby="pipeline-heading">
    <h2 id="pipeline-heading">Carteira atual por estágio</h2>
    <p>Fotografia do estágio atual dos leads. A data não se aplica a esta contagem; estágio e corretor filtram a carteira.</p>
    <div class="table-container">
        <table>
            <thead><tr><th scope="col">Estágio atual</th><th scope="col">Leads</th></tr></thead>
            <tbody>
            <?php foreach ($stages as $key => $label): ?>
                <?php $stageLeadUrl = '/leads?' . http_build_query(array_filter(['stage' => $key, 'user_id' => $filters['user_id']], static fn ($value) => $value !== '')); ?>
                <tr><th scope="row"><a href="<?= $e($stageLeadUrl) ?>"><?= $e($label) ?></a></th><td><?= $e((string)($report['stage_counts'][$key] ?? 0)) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="reports-section" aria-labelledby="interactions-heading">
    <h2 id="interactions-heading">Interações por tipo</h2>
    <p>Mesmo período e filtros de lead do indicador de interações realizadas.</p>
    <?php if ($report['interactions_by_type'] === []): ?>
        <p class="reports-empty">Nenhuma interação encontrada com os filtros selecionados.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead><tr><th scope="col">Tipo</th><th scope="col">Registros</th></tr></thead>
                <tbody>
                <?php foreach ($report['interactions_by_type'] as $row): ?>
                    <tr><th scope="row"><?= $e(ucfirst(str_replace('_', ' ', (string)$row['type']))) ?></th><td><?= $e((string)$row['total']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="reports-section" aria-labelledby="interaction-records-heading">
    <h2 id="interaction-records-heading">Registros de origem das interações</h2>
    <p>Até <?= $e((string)\App\Modules\Reports\Services\ReportsService::DETAIL_ROWS) ?> interações mais recentes. O ID e a ficha do lead permitem conferir o evento completo no histórico.</p>
    <?php if ($report['interaction_details'] === []): ?>
        <p class="reports-empty">Nenhuma interação encontrada com os filtros selecionados.</p>
    <?php else: ?>
        <div class="table-container reports-table-scroll" role="region" aria-label="Tabela de interações de origem, com rolagem horizontal" tabindex="0">
            <table>
                <thead><tr><th scope="col">Interação</th><th scope="col">Lead</th><th scope="col">Tipo</th><th scope="col">Data do contato</th><th scope="col">Registrado por</th></tr></thead>
                <tbody>
                <?php foreach ($report['interaction_details'] as $interaction): ?>
                    <tr>
                        <td>#<?= $e((string)$interaction['id']) ?></td>
                        <th scope="row"><a href="/leads/<?= $e((string)$interaction['lead_id']) ?>"><?= $e($interaction['lead_name']) ?></a> <small>#<?= $e((string)$interaction['lead_id']) ?></small></th>
                        <td><?= $e(ucfirst(str_replace('_', ' ', (string)$interaction['type']))) ?></td>
                        <td><?= $e(date('d/m/Y H:i', strtotime((string)$interaction['contacted_at']))) ?></td>
                        <td><?= $e($interaction['actor'] ?? 'Usuário indisponível') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="reports-section" aria-labelledby="source-records-heading">
    <h2 id="source-records-heading">Leads de origem</h2>
    <p>Até <?= $e((string)\App\Modules\Reports\Services\ReportsService::DETAIL_ROWS) ?> registros mais recentes que compõem “Leads criados”. Abra a ficha para conferir o histórico completo.</p>
    <?php if ($report['lead_details'] === []): ?>
        <p class="reports-empty">Nenhum lead criado no período com os filtros selecionados.</p>
    <?php else: ?>
        <div class="table-container reports-table-scroll" role="region" aria-label="Tabela de leads de origem, com rolagem horizontal" tabindex="0">
            <table>
                <thead><tr><th scope="col">Lead</th><th scope="col">Criado em</th><th scope="col">Estágio atual</th><th scope="col">Corretor</th><th scope="col">Interações no período</th></tr></thead>
                <tbody>
                <?php foreach ($report['lead_details'] as $lead): ?>
                    <tr>
                        <th scope="row"><a href="/leads/<?= $e((string)$lead['id']) ?>"><?= $e($lead['name']) ?></a> <small>#<?= $e((string)$lead['id']) ?></small></th>
                        <td><?= $e(date('d/m/Y H:i', strtotime((string)$lead['created_at']))) ?></td>
                        <td><?= $e($stages[$lead['stage']] ?? $lead['stage']) ?></td>
                        <td><?= $e($lead['broker'] ?? 'Não atribuído') ?></td>
                        <td><?= $e((string)$lead['interactions_in_period']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<details class="reports-definitions">
    <summary>Definições e limites deste relatório</summary>
    <ul>
        <li><strong>Leads criados:</strong> quantidade de linhas em <code>leads</code> com <code>created_at</code> entre as datas inclusivas; estágio e responsável atuais são aplicados como filtros. Duplicidades mescladas não contam como novo registro.</li>
        <li><strong>Interações realizadas:</strong> quantidade de linhas em <code>lead_interactions</code> por <code>contacted_at</code> no período. Uma linha representa um contato registrado; reenvios idempotentes não geram contagem adicional.</li>
        <li><strong>Carteira por estágio:</strong> quantidade atual de leads em cada estágio; não representa entradas ou transições históricas. Filtro de data não se aplica.</li>
        <li>Datas usam o fuso configurado em <code>APP_TIMEZONE</code> (padrão: America/Sao_Paulo). Período máximo: <?= $e((string)\App\Modules\Reports\Services\ReportsService::MAX_PERIOD_DAYS) ?> dias. Cada conjunto de dados exportado é limitado a <?= $e((string)\App\Modules\Reports\Services\ReportsService::MAX_EXPORT_ROWS) ?> linhas; a exportação é registrada na auditoria.</li>
    </ul>
</details>
