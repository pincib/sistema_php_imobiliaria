<header class="dashboard-home-header">
    <div>
        <h1>Painel de Controle</h1>
        <p class="dashboard-home-meta">
            Olá, <strong><?= $e($user['name']) ?></strong>! Você está autenticado como 
            <span class="badge badge-<?= $e($user['role']) ?>"><?= $e($user['role_label']) ?></span>.
            &bull; <?= $e(date('d/m/Y')) ?>
        </p>
    </div>
    <div>
        <a href="/leads/new" role="button" class="primary dashboard-new-lead">
            + Novo Lead
        </a>
    </div>
</header>

<!-- =======================================================================
     SEÇÃO CENTRAL: AGENDA DIÁRIA DE RETORNOS E FOLLOW-UPS
     ======================================================================= -->
<section class="dashboard-home-agenda">
    <div class="dashboard-home-section-heading">
        <div>
            <h2>Agenda Operacional de Follow-ups</h2>
            <p>
                Priorize contatos em atraso e organize seus retornos programados para não perder negociações.
            </p>
        </div>
    </div>

    <!-- Container Reativo com HTMX -->
    <?php require __DIR__ . '/partials/follow_ups_container.php'; ?>
</section>

<p class="dashboard-loading htmx-indicator" id="management-summary-loading" role="status">Atualizando resumo gerencial…</p>
<?php require __DIR__ . '/partials/management_summary.php'; ?>
<!-- =======================================================================
     SEÇÃO DE INDICADORES GERAIS DA OPERAÇÃO (Personalizados por Papel)
     ======================================================================= -->
<?php if (($user['role'] ?? '') === 'admin'): ?>
    <div class="grid dashboard-home-admin-stats">
        <div class="stat-card">
            <h3><?= $e((string)($stats['total_leads'] ?? 0)) ?></h3>
            <p>Leads no Funil</p>
        </div>
        <div class="stat-card">
            <h3><?= $e((string)$stats['active_users']) ?></h3>
            <p>Usuários Ativos</p>
        </div>
        <div class="stat-card">
            <h3><?= $e((string)$stats['active_sessions']) ?></h3>
            <p>Sessões Concorrentes</p>
        </div>
        <div class="stat-card">
            <h3><?= $e((string)$stats['audit_events']) ?></h3>
            <p>Eventos Auditados</p>
        </div>
    </div>
<?php endif; ?>

<section class="dashboard-home-modules">
    <h2>Módulos do Sistema</h2>
    <div class="grid">
        <article>
            <h3>Leads e Oportunidades</h3>
            <p>Centralize contatos de clientes, histórico de conversas, pipeline comercial e agenda de follow-ups.</p>
            <footer>
                <a href="/leads" role="button" class="contrast">Acessar Leads</a>
            </footer>
        </article>

        <article>
            <h3>Imóveis</h3>
            <p>Controle do catálogo de imóveis, especificações, proprietários e cruzamento com interessados.</p>
            <footer>
                <a href="/properties" role="button" class="secondary">Acessar Imóveis</a>
            </footer>
        </article>

        <article>
            <h3>Placas e Faixas</h3>
            <p>Gerenciamento do estoque de placas instaladas, locais, vistorias e retiradas programadas.</p>
            <footer>
                <a href="/signboards" role="button" class="secondary">Acessar Placas</a>
            </footer>
        </article>
    </div>
</section>

<?php if (($user['role'] ?? '') === 'admin'): ?>
    <section class="dashboard-home-administration">
        <h2>Administração e Governança</h2>
        <div class="grid">
            <article>
                <h3>Gestão de Usuários</h3>
                <p>Gerencie o cadastro de corretores, atribuição de perfis de acesso e bloqueio de sessões.</p>
                <footer>
                    <a href="/admin/users" role="button" class="outline">Gerenciar Usuários</a>
                </footer>
            </article>

            <article>
                <h3>Trilha de Auditoria</h3>
                <p>Visualize o histórico completo e imutável de acessos, alterações de cadastro e permissões.</p>
                <footer>
                    <a href="/admin/audit" role="button" class="outline">Ver Logs de Auditoria</a>
                </footer>
            </article>
        </div>
    </section>
<?php endif; ?>
