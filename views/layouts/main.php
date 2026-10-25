<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <title><?= $e($title ?? 'CRM Imobiliário') ?></title>
    <link rel="stylesheet" href="/assets/css/pico.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/css/app.css') ?>">
    <script src="/assets/js/htmx.min.js"></script>
    <script src="/assets/js/dashboard-partial-feedback.js?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/js/dashboard-partial-feedback.js') ?>" defer></script>
    <script>
        // Configura HTMX para enviar o token CSRF automaticamente em todas as requisições AJAX
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
    <header class="app-header">
        <div class="container">
            <nav aria-label="Navegação principal">
                <ul>
                    <li>
                        <a href="/" class="brand-title">
                            <strong>Plataforma Pinciara</strong>
                        </a>
                    </li>
                </ul>
                <ul class="app-primary-links">
                    <li><a href="/" class="<?= ($_SERVER['REQUEST_URI'] ?? '') === '/' ? 'active' : '' ?>">Dashboard</a></li>
                    <li><a href="/leads" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/leads') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard/leads') ? 'active' : '' ?>">Leads</a></li>
                    <li><a href="/properties" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/properties') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard/properties') ? 'active' : '' ?>">Imóveis</a></li>
                    <li><a href="/owners" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/owners') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard/owners') ? 'active' : '' ?>">Proprietários</a></li>
                    <li><a href="/signboards" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/signboards') ? 'active' : '' ?>">Placas</a></li>
                    
                    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                        <li><a href="/reports" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/reports') ? 'active' : '' ?>">Relatórios</a></li>
                        <li><a href="/admin/users" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/users') ? 'active' : '' ?>">Usuários</a></li>
                        <li><a href="/admin/audit" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/audit') ? 'active' : '' ?>">Auditoria</a></li>
                        <li><a href="/admin/system" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/system') ? 'active' : '' ?>">Sistema</a></li>
                    <?php endif; ?>
                </ul>
                <ul class="app-account-links">
                    <?php if ($currentUser): ?>
                        <li>
                            <span class="user-info">
                                <strong><?= $e($currentUser['name']) ?></strong>
                                <span class="badge badge-<?= $e($currentUser['role']) ?>">
                                    <?= $e($currentUser['role_label']) ?>
                                </span>
                            </span>
                        </li>
                        <li>
                            <form action="/logout" method="POST" style="margin: 0; display: inline;">
                                <?= $csrfField ?>
                                <button type="submit" class="outline secondary" style="padding: 0.35rem 0.75rem; font-size: 0.85rem; margin: 0;">Sair</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li><a href="/login" role="button" class="outline">Entrar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
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

    <footer class="container" style="padding-top: 1rem; border-top: 1px solid var(--pico-muted-border-color); text-align: center; font-size: 0.8rem; color: var(--pico-muted-color);">
        <p>Plataforma Pinciara &bull; CRM Imobiliário Local-First &bull; Modo Seguro &bull; SQLite WAL</p>
    </footer>
</body>
</html>
