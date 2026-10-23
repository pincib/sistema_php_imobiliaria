<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <title><?= $e($title ?? 'Entrar — Plataforma Pinciara') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏢</text></svg>">
    <link rel="stylesheet" href="/assets/css/pico.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <header class="auth-header">
                <h1>Plataforma Pinciara</h1>
                <p>CRM Imobiliário &bull; Acesso à Rede Local</p>
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

            <article>
                <?= $content ?>
            </article>

            <footer class="auth-footer">
                <p>
                    Rede local segura &bull; Acesso exclusivo para corretores e administradores.<br>
                    <small>Para novos acessos ou recuperação de senha, consulte o gestor da imobiliária.</small>
                </p>
            </footer>
        </div>
    </div>
</body>
</html>
