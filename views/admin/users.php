<header style="margin-bottom: 2rem;">
    <h1>Gestão de Usuários e Permissões</h1>
    <p style="color: var(--pico-muted-color);">
        Cadastre corretores e administradores, ajuste permissões e gerencie o acesso à plataforma.
    </p>
</header>

<div class="grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Lista de Usuários Existentes -->
    <section>
        <article>
            <header>
                <h3 style="margin: 0;">Usuários Cadastrados</h3>
            </header>
            <div style="overflow-x: auto;">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Papel</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= $e($u['name']) ?></strong>
                                    <?php if ($u['id'] === $currentUser['id']): ?>
                                        <small style="color: var(--pico-muted-color);">(você)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $e($u['email']) ?></td>
                                <td>
                                    <form action="/admin/users/<?= $e((string)$u['id']) ?>/role" method="POST" style="margin: 0; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <?= $csrfField ?>
                                        <select name="role_id" onchange="this.form.submit()" style="margin: 0; padding: 0.2rem 0.5rem; font-size: 0.8rem; width: auto;">
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?= $e((string)$r['id']) ?>" <?= (int)$u['role_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                                                    <?= $e($r['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="badge badge-active">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['id'] !== $currentUser['id']): ?>
                                        <form action="/admin/users/<?= $e((string)$u['id']) ?>/toggle-active" method="POST" style="margin: 0; display: inline;">
                                            <?= $csrfField ?>
                                            <button type="submit" class="outline <?= (int)$u['is_active'] === 1 ? 'secondary' : 'contrast' ?>" style="padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0;">
                                                <?= (int)$u['is_active'] === 1 ? 'Desativar' : 'Ativar' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: var(--pico-muted-color);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <!-- Formulário de Novo Usuário -->
    <aside>
        <article>
            <header>
                <h3 style="margin: 0;">Novo Usuário</h3>
            </header>
            <form action="/admin/users" method="POST">
                <?= $csrfField ?>

                <label for="name">
                    Nome Completo
                    <input type="text" id="name" name="name" placeholder="Ex: Roberto Pinciara" required>
                </label>

                <label for="email">
                    E-mail de Acesso
                    <input type="email" id="email" name="email" placeholder="usuario@pinciara.com" required>
                </label>

                <label for="password">
                    Senha Temporária (mín. 8 caracteres)
                    <input type="password" id="password" name="password" placeholder="••••••••" minlength="8" required>
                </label>

                <label for="role_id">
                    Papel / Permissão
                    <select id="role_id" name="role_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $e((string)$r['id']) ?>" <?= $r['name'] === 'corretor' ? 'selected' : '' ?>>
                                <?= $e($r['label']) ?><?= !empty($r['description']) ? ' (' . $e($r['description']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit" style="width: 100%; margin-top: 1rem;">Cadastrar Usuário</button>
            </form>
        </article>
    </aside>
</div>
