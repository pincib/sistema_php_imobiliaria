<div style="text-align: center; padding: 3rem 1rem;">
    <h1 style="font-size: 3rem; margin-bottom: 0.5rem; color: #dc2626;">403</h1>
    <h2>Acesso Proibido</h2>
    <p style="color: var(--pico-muted-color); max-width: 500px; margin: 0 auto 2rem auto;">
        <?= $e($message ?? 'Seu perfil de usuário não possui as permissões necessárias para acessar este recurso.') ?>
    </p>
    <div>
        <a href="/" role="button">Voltar ao Painel Principal</a>
    </div>
</div>
