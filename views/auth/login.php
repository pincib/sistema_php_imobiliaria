<form action="/login" method="POST" id="login-form">
    <?= $csrfField ?>

    <label for="email">
        E-mail Corporativo
        <input 
            type="email" 
            id="email" 
            name="email" 
            value="<?= $e($oldEmail ?? '') ?>"
            placeholder="corretor@pinciara.com" 
            maxlength="254"
            required 
            autofocus 
            autocomplete="email"
            <?= !empty($flashes['error'] ?? null) ? 'aria-invalid="true" aria-describedby="auth-alert-message"' : '' ?>
        >
    </label>

    <label for="password">
        Senha
        <div class="password-input-group">
            <input 
                type="password" 
                id="password" 
                name="password" 
                placeholder="••••••••" 
                maxlength="128"
                required 
                autocomplete="current-password"
                <?= !empty($flashes['error'] ?? null) ? 'aria-invalid="true" aria-describedby="auth-alert-message"' : '' ?>
            >
            <button 
                type="button" 
                id="toggle-password" 
                class="password-toggle-btn" 
                aria-label="Alternar visibilidade da senha"
                aria-pressed="false"
                aria-controls="password"
                title="Mostrar ou ocultar senha"
            >
                Mostrar
            </button>
        </div>
        <div id="caps-warning" class="caps-lock-hint" role="status" aria-live="polite">
            Caps Lock ativado. Verifique antes de entrar.
        </div>
    </label>

    <button type="submit" id="btn-submit" class="auth-submit-btn">
        Entrar na Plataforma
    </button>
</form>

<script>
    // 1. Alternador de Visibilidade da Senha com Estado Acessível (aria-pressed)
    const toggleBtn = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleBtn.textContent = isPassword ? 'Ocultar' : 'Mostrar';
            toggleBtn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            passwordInput.focus();
        });
    }

    // 2. Detecção Resiliente de Caps Lock Ativo
    const capsWarning = document.getElementById('caps-warning');
    if (passwordInput && capsWarning) {
        passwordInput.addEventListener('keyup', function (e) {
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                capsWarning.classList.add('is-visible');
            } else {
                capsWarning.classList.remove('is-visible');
            }
        });
        passwordInput.addEventListener('blur', function () {
            capsWarning.classList.remove('is-visible');
        });
    }

    // 3. Prevenção de Envio Duplo e Feedback de Carregamento
    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('btn-submit');

    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.setAttribute('disabled', 'disabled');
            submitBtn.textContent = 'Autenticando...';
        });
    }
</script>
