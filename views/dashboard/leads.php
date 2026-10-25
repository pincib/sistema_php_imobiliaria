<nav class="dashboard-breadcrumbs" aria-label="Navegação estrutural">
    <ol>
        <li><a href="/">Painel principal</a></li>
        <li><span aria-hidden="true">/</span><span aria-current="page">Análise de Leads</span></li>
    </ol>
</nav>
<header class="dashboard-page-header">
    <div>
        <h1>Análise de Leads</h1>
        <p>Distribuição atual do pipeline e registros que compõem cada contagem.</p>
    </div>
    <nav aria-label="Ações da análise de Leads">
        <a href="/" role="button" class="outline secondary">Voltar ao resumo</a>
        <a href="/leads" role="button" class="secondary">Abrir módulo de Leads</a>
    </nav>
</header>
<p class="dashboard-loading htmx-indicator" id="leads-dashboard-loading" role="status">Atualizando análise de Leads…</p>
<?php require __DIR__ . '/partials/leads_content.php'; ?>
