<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <title><?= $e($title ?? 'Entrar — Plataforma Pinciara') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏢</text></svg>">
    
    <!-- Design System Apple Pro & Complementary Styles -->
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/css/design-system.css') ?>">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/css/app.css') ?>">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: var(--bg-canvas);">
    <div class="auth-wrapper" style="width: 100%; max-width: 440px; padding: 1.5rem;">
        <div class="auth-card" style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-xl); padding: 2.25rem 2rem; box-shadow: var(--shadow-lg);">
            <header class="auth-header" style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #0071e3 0%, #005bb5 100%); border-radius: var(--radius-md); display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 700; font-size: 1.5rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0, 113, 227, 0.35);">
                    P
                </div>
                <h1 style="font-size: 1.65rem; font-weight: 700; margin: 0 0 0.35rem 0; letter-spacing: -0.025em;">Plataforma Pinciara</h1>
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">CRM Imobiliário &bull; Acesso à Rede Local</p>
            </header>

            <?php if (!empty($flashes)): ?>
                <section class="alerts-container">
                    <?php foreach ($flashes as $type => $messages): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= $e($type) ?>" role="alert" id="auth-alert-message">
                                <span><?= $e($msg) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <article style="border: none; box-shadow: none; padding: 0; background: transparent;">
                <?= $content ?>
            </article>

            <footer class="auth-footer" style="text-align: center; margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle); font-size: 0.78rem; color: var(--text-muted);">
                <p style="margin: 0;">
                    Rede local segura &bull; Acesso exclusivo para corretores e administradores.<br>
                    <small style="color: var(--text-muted);">Em caso de dúvidas, consulte a gestão da imobiliária.</small>
                </p>
            </footer>
        </div>
    </div>
</body>
</html>
