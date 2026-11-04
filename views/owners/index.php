<?php
/**
 * Plataforma Pinciara — Gestão de Proprietários (Apple Pro Edition)
 *
 * @var list<array<string, mixed>> $owners
 * @var string $q
 * @var string $tab
 * @var int $countDesatualizados
 * @var int $countAtualizados
 * @var int $countTotal
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var callable $e
 * @var callable $formatPhone
 * @var array<string, mixed> $currentUser
 * @var string $csrfField
 */
?>

<!-- Header do Módulo -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle);">
    <div>
        <h1 style="margin: 0 0 0.35rem 0; font-size: 1.85rem; font-weight: 700; letter-spacing: -0.025em;">
            Proprietários
        </h1>
        <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary);">
            Rotina de controle cadastral periódico e relacionamento com proprietários de imóveis.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="/kanban/owners" role="button" class="outline secondary" style="font-size: 0.85rem;">
            Quadro Kanban
        </a>
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <a href="/leads/import?dataset=owners&amp;return_to=owners" role="button" class="outline secondary" style="font-size: 0.85rem;">
                Importar CSV
            </a>
        <?php endif; ?>
        <a href="/owners/new" role="button" class="primary" style="font-size: 0.85rem; font-weight: 600;">
            + Cadastrar Proprietário
        </a>
    </div>
</div>

<!-- Barra de Abas: Desatualizados vs Atualizados (Critério de 30 dias) -->
<div class="filter-pills-bar" style="margin-bottom: 1.25rem;">
    <!-- Aba Desatualizados -->
    <a href="/owners?tab=desatualizados<?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>" 
       role="button" 
       class="<?= $tab === 'desatualizados' ? 'contrast' : 'outline secondary' ?>"
       style="display: inline-flex; align-items: center; gap: 0.45rem;">
        <span>⚠️ Desatualizados (>30 dias)</span>
        <span class="badge <?= $tab === 'desatualizados' ? 'badge-overdue' : 'badge-neutral' ?>" style="font-size: 0.72rem;">
            <?= $countDesatualizados ?>
        </span>
    </a>

    <!-- Aba Atualizados -->
    <a href="/owners?tab=atualizados<?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>" 
       role="button" 
       class="<?= $tab === 'atualizados' ? 'contrast' : 'outline secondary' ?>"
       style="display: inline-flex; align-items: center; gap: 0.45rem;">
        <span>✓ Atualizados (Válidos)</span>
        <span class="badge <?= $tab === 'atualizados' ? 'badge-completed' : 'badge-neutral' ?>" style="font-size: 0.72rem;">
            <?= $countAtualizados ?>
        </span>
    </a>

    <!-- Aba Todos -->
    <a href="/owners?tab=todos<?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>" 
       role="button" 
       class="<?= $tab === 'todos' ? 'contrast' : 'outline secondary' ?>"
       style="display: inline-flex; align-items: center; gap: 0.45rem;">
        <span>Todos</span>
        <span class="badge badge-neutral" style="font-size: 0.72rem;">
            <?= $countTotal ?>
        </span>
    </a>
</div>

<!-- Barra de Busca -->
<form class="filters-toolbar" method="GET" action="/owners" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <input type="hidden" name="tab" value="<?= $e($tab) ?>">
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px;">
            <input type="search" 
                   name="q" 
                   value="<?= $e($q) ?>" 
                   placeholder="Buscar por nome, telefone, e-mail ou código de imóvel..." 
                   style="margin: 0;">
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button type="submit" class="primary" style="margin: 0; padding: 0.55rem 1rem;">
                Buscar
            </button>
            <?php if ($q !== ''): ?>
                <a href="/owners?tab=<?= $e($tab) ?>" role="button" class="outline secondary" style="margin: 0; padding: 0.55rem 0.85rem;">
                    Limpar
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Tabela de Proprietários -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th scope="col" style="min-width: 200px;">Proprietário</th>
                <th scope="col" style="min-width: 170px;">Status de Atualização</th>
                <th scope="col" style="min-width: 170px;">Contato</th>
                <th scope="col" style="min-width: 150px;">Imóveis Vinculados</th>
                <th scope="col" style="text-align: right; min-width: 220px;">Ações Rápidas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($owners as $owner): ?>
                <?php $cleanPhone = preg_replace('/\D+/', '', $owner['phone'] ?? '') ?? ''; ?>
                <tr>
                    <!-- Proprietário -->
                    <td>
                        <a href="/owners/<?= (int)$owner['id'] ?>" style="font-weight: 700; font-size: 0.95rem; color: var(--text-main); text-decoration: none;">
                            <?= $e($owner['name']) ?>
                        </a>
                        <small style="display: block; color: var(--text-muted); font-size: 0.75rem;">
                            ID #<?= (int)$owner['id'] ?>
                        </small>
                        <?php if (!empty($owner['invalid_contact'])): ?>
                            <span class="badge badge-overdue" style="font-size: 0.68rem; margin-top: 0.25rem;">
                                Contato Inválido
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Status de Atualização (Regra de 30 dias) -->
                    <td>
                        <?php if ($owner['is_updated']): ?>
                            <span class="badge badge-completed" style="font-size: 0.72rem; margin-bottom: 0.2rem;">
                                ✓ Atualizado
                            </span>
                            <small style="display: block; color: var(--color-success-text); font-size: 0.75rem; font-weight: 500;">
                                Válido por mais <?= (int)$owner['days_remaining'] ?> dia(s)
                            </small>
                            <small style="display: block; color: var(--text-muted); font-size: 0.7rem;">
                                Até <?= $e($owner['expires_at']) ?>
                            </small>
                        <?php else: ?>
                            <span class="badge badge-overdue" style="font-size: 0.72rem; margin-bottom: 0.2rem;">
                                ⚠️ Desatualizado
                            </span>
                            <small style="display: block; color: var(--color-danger-text); font-size: 0.75rem;">
                                <?= $owner['verified_days_ago'] !== null ? 'Há ' . (int)$owner['verified_days_ago'] . ' dias sem validar' : 'Nunca validado' ?>
                            </small>
                        <?php endif; ?>
                    </td>

                    <!-- Contatos com Mensagem de WhatsApp Pronta -->
                    <td>
                        <?php if (!empty($owner['phone'])): ?>
                            <div style="font-size: 0.85rem; font-weight: 500; margin-bottom: 0.25rem;">
                                <?= $e($formatPhone($owner['phone'])) ?>
                            </div>
                            <div style="display: flex; gap: 0.35rem; align-items: center;">
                                <?php if (!empty($owner['quick_whatsapp_url'])): ?>
                                    <a href="<?= $e($owner['quick_whatsapp_url']) ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer" 
                                       class="btn-whatsapp" 
                                       style="padding: 0.2rem 0.5rem; font-size: 0.72rem; border-radius: var(--radius-xs); text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;"
                                       title="Abrir WhatsApp com texto mensal pré-montado">
                                        💬 Mensagem
                                    </a>
                                <?php endif; ?>
                                <a href="tel:<?= $e($cleanPhone) ?>" class="btn outline" style="padding: 0.2rem 0.45rem; font-size: 0.72rem; border-radius: var(--radius-xs);">
                                    📞 Ligar
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">Sem telefone</span>
                        <?php endif; ?>

                        <?php if (!empty($owner['email'])): ?>
                            <small style="display: block; color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e($owner['email']) ?>">
                                <?= $e($owner['email']) ?>
                            </small>
                        <?php endif; ?>
                    </td>

                    <!-- Imóveis Vinculados -->
                    <td>
                        <?php if (!empty($owner['property_codes'])): ?>
                            <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                                <?php foreach (explode(',', $owner['property_codes']) as $code): ?>
                                    <a href="/properties?q=<?= rawurlencode($code) ?>" class="badge badge-neutral" style="text-decoration: none; font-size: 0.75rem;">
                                        🏠 <?= $e($code) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem; margin-top: 0.2rem;">
                                Total: <?= (int)$owner['property_count'] ?> imóvel(is)
                            </small>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">— Nenhum</span>
                        <?php endif; ?>
                    </td>

                    <!-- Ações Rápidas -->
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 0.4rem; justify-content: flex-end; align-items: center;">
                            <!-- Botão Atualizado (1-Clique) -->
                            <form action="/owners/<?= (int)$owner['id'] ?>/verify" method="POST" style="margin: 0; display: inline;">
                                <?= $csrfField ?>
                                <input type="hidden" name="return_to" value="/owners?tab=<?= $e($tab) ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>">
                                <button type="submit" 
                                        class="<?= $owner['is_updated'] ? 'outline secondary' : 'primary' ?>" 
                                        style="padding: 0.4rem 0.75rem; font-size: 0.78rem; font-weight: 600;"
                                        title="<?= $owner['is_updated'] ? 'Renovar validade por mais 30 dias' : 'Confirmar que as informações deste proprietário estão atualizadas' ?>">
                                    <?= $owner['is_updated'] ? '✓ Atualizado' : 'Marcar Atualizado' ?>
                                </button>
                            </form>

                            <a href="/owners/<?= (int)$owner['id'] ?>" role="button" class="outline secondary" style="padding: 0.4rem 0.75rem; font-size: 0.78rem;">
                                Abrir
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($owners)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem 1.5rem;">
                        <div class="empty-state-box" style="border: none; padding: 0;">
                            <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <h4 style="margin-bottom: 0.35rem;">
                                <?= $tab === 'desatualizados' ? 'Nenhum proprietário desatualizado!' : 'Nenhum proprietário encontrado' ?>
                            </h4>
                            <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">
                                <?= $tab === 'desatualizados' ? 'Excelente trabalho! Toda a base de proprietários foi verificada nos últimos 30 dias.' : 'Ajuste os filtros de busca ou cadastre um novo proprietário.' ?>
                            </p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginação -->
<?php if ($pages > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            Página <strong><?= $page ?></strong> de <strong><?= $pages ?></strong> &bull; Total de <?= $total ?> proprietário(s)
        </span>
        <div style="display: flex; gap: 0.4rem;">
            <?php if ($page > 1): ?>
                <a href="?tab=<?= $e($tab) ?>&amp;q=<?= rawurlencode($q) ?>&amp;page=<?= $page - 1 ?>" role="button" class="outline secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                    &larr; Anterior
                </a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a href="?tab=<?= $e($tab) ?>&amp;q=<?= rawurlencode($q) ?>&amp;page=<?= $page + 1 ?>" role="button" class="outline secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                    Próxima &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
