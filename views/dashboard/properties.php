<nav class="dashboard-breadcrumbs" aria-label="Navegação estrutural">
    <ol>
        <li><a href="/">Painel principal</a></li>
        <li><span aria-hidden="true">/</span><span aria-current="page">Análise de Imóveis</span></li>
    </ol>
</nav>
<header class="dashboard-page-header">
    <div>
        <h1>Análise de Imóveis</h1>
        <p>Compare imóveis e tipos pelo número de Leads atualmente vinculados.</p>
    </div>
    <nav aria-label="Ações da análise de Imóveis">
        <a href="/" role="button" class="outline secondary">Voltar ao resumo</a>
        <a href="/properties" role="button" class="secondary">Abrir módulo de Imóveis</a>
    </nav>
</header>
<p class="dashboard-loading htmx-indicator" id="properties-dashboard-loading" role="status">Atualizando análise de Imóveis…</p>
<?php require __DIR__ . '/partials/properties_content.php'; ?>
