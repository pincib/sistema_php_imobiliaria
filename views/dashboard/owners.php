<nav class="dashboard-breadcrumbs" aria-label="Navegação estrutural">
    <ol>
        <li><a href="/">Painel principal</a></li>
        <li><span aria-hidden="true">/</span><span aria-current="page">Análise de Proprietários</span></li>
    </ol>
</nav>
<header class="dashboard-page-header">
    <div>
        <h1>Análise de Proprietários</h1>
        <p>Compare imóveis vinculados e valores cadastrados em rankings separados.</p>
    </div>
    <nav aria-label="Ações da análise de Proprietários">
        <a href="/" role="button" class="outline secondary">Voltar ao resumo</a>
        <a href="/owners" role="button" class="secondary">Abrir módulo de Proprietários</a>
    </nav>
</header>
<p class="dashboard-loading htmx-indicator" id="owners-dashboard-loading" role="status">Atualizando análise de Proprietários…</p>
<?php require __DIR__ . '/partials/owners_content.php'; ?>
