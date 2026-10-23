<header style="margin-bottom: 2rem;">
    <h1>Diagnóstico, Monitoramento e Backups</h1>
    <p style="color: var(--pico-muted-color);">
        Painel de saúde do sistema, integridade do banco SQLite, monitoramento de espaço e gestão de backups com retenção.
    </p>
</header>

<!-- Indicadores de Saúde e Integridade -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Card Banco de Dados -->
    <article style="margin: 0; padding: 1.25rem;">
        <header style="margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--pico-card-border-color);">
            <strong style="display: flex; align-items: center; justify-content: space-between;">
                <span>Banco de Dados SQLite</span>
                <span class="badge" style="background: <?= $system['integrity'] === 'ok' ? '#2e7d32' : '#c62828' ?>; color: #fff;">
                    <?= $system['integrity'] === 'ok' ? 'Íntegro (OK)' : 'Falha' ?>
                </span>
            </strong>
        </header>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
            <li><strong>Modo Journal:</strong> <?= $e(strtoupper($system['journal_mode'])) ?></li>
            <li><strong>Violações FK:</strong> <?= $system['fk_violations'] ?></li>
            <li><strong>Tamanho Base:</strong> <?= $e($system['db_size']) ?></li>
            <li><strong>Tamanho WAL:</strong> <?= $e($system['wal_size']) ?></li>
            <li><strong>Versão SQLite:</strong> <?= $e((string)$system['sqlite_version']) ?></li>
        </ul>
    </article>

    <!-- Card Espaço em Disco -->
    <article style="margin: 0; padding: 1.25rem;">
        <header style="margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--pico-card-border-color);">
            <strong style="display: flex; align-items: center; justify-content: space-between;">
                <span>Armazenamento & Disco</span>
                <span class="badge" style="background: <?= $system['used_disk_percent'] < 90 ? '#2e7d32' : '#f57c00' ?>; color: #fff;">
                    <?= $system['used_disk_percent'] ?>% Usado
                </span>
            </strong>
        </header>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
            <li><strong>Espaço Livre:</strong> <?= $e($system['free_disk']) ?></li>
            <li><strong>Capacidade Total:</strong> <?= $e($system['total_disk']) ?></li>
            <li><strong>Storage Gravável:</strong> <?= $system['storage_writable'] ? 'Sim (Permissões OK)' : 'Não (Erro de Permissão)' ?></li>
            <li><strong>Versão PHP:</strong> <?= $e($system['php_version']) ?></li>
        </ul>
    </article>

    <!-- Card Status de Backup -->
    <article style="margin: 0; padding: 1.25rem;">
        <header style="margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--pico-card-border-color);">
            <strong style="display: flex; align-items: center; justify-content: space-between;">
                <span>Rotina de Backups</span>
                <?php if ($latestBackup): ?>
                    <span class="badge" style="background: <?= $latestBackup['age_hours'] <= 24 ? '#2e7d32' : '#f57c00' ?>; color: #fff;">
                        <?= $latestBackup['age_hours'] <= 24 ? 'Em Dia' : 'Atenção (>24h)' ?>
                    </span>
                <?php else: ?>
                    <span class="badge" style="background: #c62828; color: #fff;">Sem Backup</span>
                <?php endif; ?>
            </strong>
        </header>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
            <li><strong>Total de Arquivos:</strong> <?= count($backups) ?></li>
            <?php if ($latestBackup): ?>
                <li><strong>Último:</strong> <?= $e($latestBackup['filename']) ?></li>
                <li><strong>Data:</strong> <?= $e($latestBackup['created_at']) ?></li>
                <li><strong>Tamanho:</strong> <?= $e($latestBackup['size_formatted']) ?></li>
            <?php else: ?>
                <li><em>Nenhum backup registrado até o momento.</em></li>
            <?php endif; ?>
        </ul>
    </article>
</div>

<!-- Ações Rápidas de Operação -->
<section style="margin-bottom: 2.5rem;">
    <article style="padding: 1.5rem; background: var(--pico-card-sectioning-background-color);">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            <div>
                <h4 style="margin: 0 0 0.25rem 0;">Ações Operacionais de Proteção</h4>
                <p style="margin: 0; font-size: 0.9rem; color: var(--pico-muted-color);">
                    Execute backups consistentes via VACUUM INTO ou valide o teste de recuperação em ambiente isolado.
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <form action="/admin/system/backup" method="POST" style="margin: 0;">
                    <?= $csrfField ?>
                    <button type="submit" style="margin: 0;">
                        Gerar Backup Agora
                    </button>
                </form>

                <form action="/admin/system/test-restore" method="POST" style="margin: 0;">
                    <?= $csrfField ?>
                    <button type="submit" class="outline contrast" style="margin: 0;">
                        Simular Teste de Restauração
                    </button>
                </form>
            </div>
        </div>
    </article>
</section>

<!-- Lista de Backups Existentes -->
<section style="margin-bottom: 3rem;">
    <article>
        <header>
            <h3 style="margin: 0;">Histórico de Backups Disponíveis</h3>
        </header>
        <?php if (empty($backups)): ?>
            <p style="color: var(--pico-muted-color); margin: 1rem 0;">Nenhum backup encontrado no diretório <code>storage/backups</code>.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Tipo</th>
                            <th>Tamanho</th>
                            <th>Criado em</th>
                            <th>Idade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td>
                                    <code><?= $e($b['filename']) ?></code>
                                </td>
                                <td>
                                    <?php if ($b['is_snapshot']): ?>
                                        <span class="badge" style="background: #e0e0e0; color: #333; font-size: 0.75rem;">Snapshot Pré-Restauração</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #1976d2; color: #fff; font-size: 0.75rem;">Backup Diário</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $e($b['size_formatted']) ?></td>
                                <td><?= $e($b['created_at']) ?></td>
                                <td>há <?= $e((string)$b['age_hours']) ?> horas</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>

<!-- Logs de Backup e Operações Recentes -->
<section>
    <article>
        <header>
            <h3 style="margin: 0;">Log de Operações de Backup (storage/logs/backup.log)</h3>
        </header>
        <?php if (empty($recentLogs)): ?>
            <p style="color: var(--pico-muted-color); margin: 1rem 0;">Nenhum registro de log encontrado.</p>
        <?php else: ?>
            <pre style="background: #111927; color: #e5e7eb; padding: 1rem; border-radius: 6px; font-size: 0.85rem; max-height: 250px; overflow-y: auto;"><?php foreach ($recentLogs as $log): ?><?= $e($log) . "\n" ?><?php endforeach; ?></pre>
        <?php endif; ?>
    </article>
</section>
