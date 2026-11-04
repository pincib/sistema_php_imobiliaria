<?php
/**
 * Plataforma Pinciara — Dashboard Principal
 * Visão Operacional e Estratégica (Steve Jobs Aesthetic Edition)
 *
 * @var array<string, mixed> $user
 * @var array<string, mixed> $stats
 * @var callable $e
 */
?>

<!-- =======================================================================
     CABECALHO EXECUTIVO / HERO DE BOAS-VINDAS
     ======================================================================= -->
<header class="dashboard-home-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle);">
    <div>
        <h1 style="margin: 0 0 0.35rem 0; font-size: 1.85rem; font-weight: 700; letter-spacing: -0.025em;">
            Painel de Controle
        </h1>
        <p class="dashboard-home-meta" style="margin: 0; font-size: 0.9rem; color: var(--text-secondary);">
            Bem-vindo de volta, <strong><?= $e($user['name']) ?></strong>! Perfil ativo: 
            <span class="badge badge-<?= $e($user['role']) ?>"><?= $e($user['role_label']) ?></span>
            &bull; <?= $e(date('d/m/Y')) ?>
        </p>
    </div>
    <div>
        <a href="/leads/new" role="button" class="primary dashboard-new-lead" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.15rem; font-weight: 600;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            + Novo Lead
        </a>
    </div>
</header>

<!-- =======================================================================
     SEÇÃO CENTRAL: AGENDA OPERACIONAL DE RETORNOS E FOLLOW-UPS
     ======================================================================= -->
<section class="dashboard-home-agenda" style="margin-bottom: 3rem;">
    <div class="dashboard-home-section-heading" style="margin-bottom: 1.25rem;">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 0.25rem;">
                Agenda Operacional de Follow-ups
            </h2>
            <p style="margin: 0; font-size: 0.88rem; color: var(--text-secondary);">
                Priorize contatos em atraso e execute seus retornos programados com ação em 1 clique.
            </p>
        </div>
    </div>

    <!-- Container Reativo com HTMX -->
    <?php require __DIR__ . '/partials/follow_ups_container.php'; ?>
</section>

<p class="dashboard-loading htmx-indicator" id="management-summary-loading" role="status">Atualizando resumo gerencial…</p>
<?php require __DIR__ . '/partials/management_summary.php'; ?>

<!-- =======================================================================
     INDICADORES GERAIS DA OPERAÇÃO (Personalizados por Papel)
     ======================================================================= -->
<?php if (($user['role'] ?? '') === 'admin'): ?>
    <section style="margin-bottom: 3rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Indicadores Operacionais</h2>
        <div class="grid dashboard-home-admin-stats">
            <div class="stat-card">
                <h3><?= $e((string)($stats['total_leads'] ?? 0)) ?></h3>
                <p>Leads no Funil</p>
                <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">Total acumulado na base</small>
            </div>
            <div class="stat-card">
                <h3><?= $e((string)$stats['active_users']) ?></h3>
                <p>Usuários Ativos</p>
                <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">Corretores e gestores</small>
            </div>
            <div class="stat-card">
                <h3><?= $e((string)$stats['active_sessions']) ?></h3>
                <p>Sessões Concorrentes</p>
                <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">Últimas 8 horas de atividade</small>
            </div>
            <div class="stat-card">
                <h3><?= $e((string)$stats['audit_events']) ?></h3>
                <p>Eventos Auditados</p>
                <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">Rastreabilidade completa</small>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- =======================================================================
     MÓDULOS DO SISTEMA (Bento Grid Apple Pro)
     ======================================================================= -->
<section class="dashboard-home-modules" style="margin-bottom: 3rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Módulos de Trabalho</h2>
    <div class="grid">
        <article style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: var(--accent-subtle); color: var(--accent); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <h3 style="margin-bottom: 0.4rem; font-size: 1.15rem;">Leads & Oportunidades</h3>
                <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.45;">
                    Centralize contatos de clientes, histórico de conversas, pipeline comercial e agenda de follow-ups.
                </p>
            </div>
            <footer style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle);">
                <a href="/leads" role="button" class="contrast" style="width: 100%; text-align: center;">Acessar Leads</a>
            </footer>
        </article>

        <article style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(16, 185, 129, 0.12); color: #059669; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </div>
                <h3 style="margin-bottom: 0.4rem; font-size: 1.15rem;">Catálogo de Imóveis</h3>
                <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.45;">
                    Controle do catálogo de imóveis, especificações, proprietários e cruzamento com interessados.
                </p>
            </div>
            <footer style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle);">
                <a href="/properties" role="button" class="secondary" style="width: 100%; text-align: center;">Acessar Imóveis</a>
            </footer>
        </article>

        <article style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(245, 158, 11, 0.12); color: #d97706; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                </div>
                <h3 style="margin-bottom: 0.4rem; font-size: 1.15rem;">Placas & Faixas</h3>
                <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.45;">
                    Gerenciamento do estoque de placas instaladas, locais, vistorias e retiradas programadas.
                </p>
            </div>
            <footer style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle);">
                <a href="/signboards" role="button" class="secondary" style="width: 100%; text-align: center;">Acessar Placas</a>
            </footer>
        </article>
    </div>
</section>

<!-- =======================================================================
     ADMINISTRAÇÃO E GOVERNANÇA (Admin Only)
     ======================================================================= -->
<?php if (($user['role'] ?? '') === 'admin'): ?>
    <section class="dashboard-home-administration" style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Administração e Governança</h2>
        <div class="grid">
            <article style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="margin-bottom: 0.4rem; font-size: 1.1rem;">Gestão de Usuários</h3>
                    <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.45;">
                        Gerencie o cadastro de corretores, atribuição de perfis de acesso e bloqueio de sessões.
                    </p>
                </div>
                <footer style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle);">
                    <a href="/admin/users" role="button" class="outline" style="width: 100%; text-align: center;">Gerenciar Usuários</a>
                </footer>
            </article>

            <article style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="margin-bottom: 0.4rem; font-size: 1.1rem;">Trilha de Auditoria</h3>
                    <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.45;">
                        Visualize o histórico completo e imutável de acessos, alterações de cadastro e permissões.
                    </p>
                </div>
                <footer style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle);">
                    <a href="/admin/audit" role="button" class="outline" style="width: 100%; text-align: center;">Ver Logs de Auditoria</a>
                </footer>
            </article>
        </div>
    </section>
<?php endif; ?>
