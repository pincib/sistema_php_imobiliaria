<header style="margin-bottom: 2rem;">
    <h1>Trilha de Auditoria</h1>
    <p style="color: var(--pico-muted-color);">
        Registro cronológico e imutável de eventos de autenticação, mudanças cadastrais e segurança.
    </p>
</header>

<article style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
    <form method="GET" action="/admin/audit" style="display: flex; gap: 1rem; align-items: flex-end; margin: 0;">
        <label style="flex: 1; margin: 0;">
            Filtrar por Ação
            <input 
                type="text" 
                name="action" 
                value="<?= $e($currentAction ?? '') ?>" 
                placeholder="Ex: auth.login, user.create, user.role_change"
                style="margin: 0;"
            >
        </label>
        <button type="submit" style="margin: 0; width: auto;">Filtrar</button>
        <?php if (!empty($currentAction)): ?>
            <a href="/admin/audit" role="button" class="outline secondary" style="margin: 0; width: auto;">Limpar</a>
        <?php endif; ?>
    </form>
</article>

<article>
    <div style="overflow-x: auto;">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Data / Hora</th>
                    <th>Ação</th>
                    <th>Usuário Autor</th>
                    <th>Entidade</th>
                    <th>IP / Origem</th>
                    <th>Detalhes (JSON)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--pico-muted-color); padding: 2rem;">
                            Nenhum registro de auditoria encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.8rem;">
                                <?= $e($log['created_at']) ?>
                            </td>
                            <td>
                                <code><?= $e($log['action']) ?></code>
                            </td>
                            <td>
                                <?php if (!empty($log['user_name'])): ?>
                                    <strong><?= $e($log['user_name']) ?></strong><br>
                                    <small style="color: var(--pico-muted-color);"><?= $e($log['user_email']) ?></small>
                                <?php else: ?>
                                    <small style="color: var(--pico-muted-color);">Sistema / Anônimo</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['entity_type'])): ?>
                                    <span class="badge"><?= $e($log['entity_type']) ?> #<?= $e($log['entity_id'] ?? '-') ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem;">
                                <?= $e($log['ip_address'] ?? '127.0.0.1') ?>
                            </td>
                            <td style="font-size: 0.8rem; max-width: 320px;">
                                <?php if (!empty($log['new_values']) || !empty($log['old_values'])): ?>
                                    <details style="margin: 0;">
                                        <summary style="cursor: pointer; font-size: 0.75rem;">Ver Alterações</summary>
                                        <?php if (!empty($log['old_values'])): ?>
                                            <div style="margin-top: 0.25rem;">
                                                <small><strong>Antes:</strong></small>
                                                <pre style="margin: 0; padding: 0.25rem; font-size: 0.7rem;"><?= $e($log['old_values']) ?></pre>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($log['new_values'])): ?>
                                            <div style="margin-top: 0.25rem;">
                                                <small><strong>Depois:</strong></small>
                                                <pre style="margin: 0; padding: 0.25rem; font-size: 0.7rem;"><?= $e($log['new_values']) ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </details>
                                <?php else: ?>
                                    <span style="color: var(--pico-muted-color);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($total > $perPage): ?>
        <footer style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem;">
            <small style="color: var(--pico-muted-color);">Total: <?= $e((string)$total) ?> registros</small>
            <div style="display: flex; gap: 0.5rem;">
                <?php if ($currentPage > 1): ?>
                    <a href="/admin/audit?page=<?= $currentPage - 1 ?><?= !empty($currentAction) ? '&action=' . urlencode($currentAction) : '' ?>" role="button" class="outline secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Anterior</a>
                <?php endif; ?>
                <?php if (($currentPage * $perPage) < $total): ?>
                    <a href="/admin/audit?page=<?= $currentPage + 1 ?><?= !empty($currentAction) ? '&action=' . urlencode($currentAction) : '' ?>" role="button" class="outline secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Próxima</a>
                <?php endif; ?>
            </div>
        </footer>
    <?php endif; ?>
</article>
