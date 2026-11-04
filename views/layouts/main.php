<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <title><?= $e($title ?? 'Plataforma Pinciara') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏢</text></svg>">
    
    <!-- Design System Apple Pro & Complementary Styles -->
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/css/design-system.css') ?>">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/css/app.css') ?>">
    
    <script src="/assets/js/htmx.min.js?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/js/htmx.min.js') ?>" defer></script>
    <script src="/assets/js/dashboard-partial-feedback.js?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/js/dashboard-partial-feedback.js') ?>" defer></script>
    
    <script>
        // Theme synchronization (Local-first & User-preference)
        (function() {
            const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', current);
            localStorage.setItem('theme', current);
        }

        // Configuração HTMX & Resiliência
        document.addEventListener('DOMContentLoaded', function () {
            document.body.addEventListener('htmx:configRequest', function (evt) {
                evt.detail.headers['X-CSRF-Token'] = '<?= $e($csrfToken) ?>';
            });

            // Resiliência de Rede & Erros HTMX
            document.body.addEventListener('htmx:responseError', function (evt) {
                const errDiv = document.getElementById('dashboard-error');
                const errMsg = document.getElementById('dashboard-error-message');
                if (errDiv && errMsg) {
                    errMsg.textContent = 'Erro ao processar dados (' + evt.detail.xhr.status + '). Tente novamente.';
                    errDiv.style.display = 'block';
                }
            });

            document.body.addEventListener('htmx:sendError', function (evt) {
                const errDiv = document.getElementById('dashboard-error');
                const errMsg = document.getElementById('dashboard-error-message');
                if (errDiv && errMsg) {
                    errMsg.textContent = 'Falha de comunicação com o servidor. Verifique sua conexão e tente novamente.';
                    errDiv.style.display = 'block';
                }
            });

            document.body.addEventListener('htmx:beforeRequest', function () {
                const errDiv = document.getElementById('dashboard-error');
                if (errDiv) {
                    errDiv.style.display = 'none';
                }
            });
        });
    </script>
</head>
<body>
    <?php
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $userRole = $currentUser['role'] ?? 'corretor';
        
        // Iniciais para o avatar
        $userName = $currentUser['name'] ?? 'Usuário';
        $nameParts = explode(' ', trim($userName));
        $initials = mb_substr($nameParts[0], 0, 1);
        if (count($nameParts) > 1) {
            $initials .= mb_substr(end($nameParts), 0, 1);
        }
        $initials = mb_strtoupper($initials);
    ?>

    <div class="app-shell">
        <!-- Sidebar Vertical Apple Pro -->
        <aside class="app-sidebar" id="app-sidebar">
            <div class="sidebar-header">
                <a href="/" class="sidebar-brand">
                    <div class="brand-icon">P</div>
                    <div class="brand-text-group">
                        <span class="brand-name">Pinciara</span>
                        <span class="brand-caption">Imobiliária</span>
                    </div>
                </a>
            </div>

            <nav class="sidebar-nav" aria-label="Navegação principal">
                <!-- Seção Operação -->
                <div class="nav-section">
                    <div class="nav-section-title">Operação</div>
                    
                    <a href="/" class="sidebar-nav-item <?= $uri === '/' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="/leads" class="sidebar-nav-item <?= str_starts_with($uri, '/leads') || str_starts_with($uri, '/dashboard/leads') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <span>Leads & Funil</span>
                    </a>

                    <a href="/properties" class="sidebar-nav-item <?= str_starts_with($uri, '/properties') || str_starts_with($uri, '/dashboard/properties') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span>Imóveis</span>
                    </a>

                    <a href="/owners" class="sidebar-nav-item <?= str_starts_with($uri, '/owners') || str_starts_with($uri, '/dashboard/owners') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <span>Proprietários</span>
                    </a>

                    <a href="/signboards" class="sidebar-nav-item <?= str_starts_with($uri, '/signboards') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                        <span>Placas & Faixas</span>
                    </a>
                </div>

                <!-- Seção Governança (Admin Only) -->
                <?php if ($userRole === 'admin'): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Governança</div>

                        <a href="/reports" class="sidebar-nav-item <?= str_starts_with($uri, '/reports') ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                            <span>Relatórios</span>
                        </a>

                        <a href="/admin/users" class="sidebar-nav-item <?= str_starts_with($uri, '/admin/users') ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                            <span>Usuários</span>
                        </a>

                        <a href="/admin/audit" class="sidebar-nav-item <?= str_starts_with($uri, '/admin/audit') ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            <span>Auditoria</span>
                        </a>

                        <a href="/admin/system" class="sidebar-nav-item <?= str_starts_with($uri, '/admin/system') ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                            <span>Sistema</span>
                        </a>
                    </div>
                <?php endif; ?>
            </nav>

            <!-- Rodapé do Usuário -->
            <div class="sidebar-footer">
                <?php if ($currentUser): ?>
                    <div class="user-profile-widget">
                        <div class="user-avatar-initials"><?= $e($initials) ?></div>
                        <div class="user-info-text">
                            <span class="user-display-name"><?= $e($currentUser['name']) ?></span>
                            <span class="user-role-tag"><?= $e($currentUser['role_label']) ?></span>
                        </div>
                        <form action="/logout" method="POST" style="margin: 0;">
                            <?= $csrfField ?>
                            <button type="submit" class="btn-sidebar-logout" title="Sair da sessão" aria-label="Sair">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <a href="/login" class="primary" role="button" style="width: 100%; text-align: center;">Entrar</a>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Área Principal de Trabalho -->
        <div class="app-main-area">
            <!-- Command Header -->
            <header class="app-command-header">
                <div class="header-left">
                    <button type="button" class="mobile-menu-toggle" onclick="document.getElementById('app-sidebar').classList.toggle('is-open')" aria-label="Menu principal">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <span class="header-page-title"><?= $e($title ?? 'Plataforma Pinciara') ?></span>
                </div>

                <div class="header-right">
                    <span class="header-date-badge"><?= $e(date('d/m/Y')) ?></span>
                    
                    <button type="button" class="btn outline" style="padding: 0.35rem 0.65rem;" onclick="toggleTheme()" title="Alternar tema claro/escuro" aria-label="Alternar tema">
                        🌓
                    </button>

                    <?php if ($uri !== '/leads/new'): ?>
                        <a href="/leads/new" class="primary" role="button" style="padding: 0.45rem 0.9rem; font-size: 0.825rem; font-weight: 600;">
                            + Novo Lead
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Área Central de Conteúdo -->
            <main class="app-workspace container" id="main-content">
                <p id="dashboard-async-status" class="dashboard-visually-hidden" role="status" aria-live="polite" aria-atomic="true"></p>
                
                <!-- Mensagens Flash de Sucesso / Erro / Informação -->
                <?php if (!empty($flashes)): ?>
                    <section class="alerts-container">
                        <?php foreach ($flashes as $type => $messages): ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="alert alert-<?= $e($type) ?>" role="alert">
                                    <span><?= $e($msg) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <?= $content ?>
            </main>

            <footer class="app-footer">
                <p>Plataforma Pinciara &bull; CRM Imobiliário Local-First &bull; Modo Seguro &bull; SQLite WAL</p>
            </footer>
        </div>
    </div>
</body>
</html>
