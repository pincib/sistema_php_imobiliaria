<?php
/**
 * @var list<array<string, mixed>> $leads
 * @var int $total
 * @var int $totalInDatabase
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var array<string, string> $filters
 * @var callable $e
 * @var callable $formatPhone
 * @var callable $getStageBadge
 * @var callable $getStageLabel
 */

$startItem = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$endItem = min($total, $page * $perPage);
?>

<div class="table-loading-overlay">
    <?php if ($total === 0): ?>
        <?php if ($totalInDatabase === 0): ?>
            <!-- Estado Vazio (Empty State): Sem nenhum lead no sistema -->
            <div class="state-box">
                <div class="state-box-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <line x1="19" y1="8" x2="19" y2="14"></line>
                        <line x1="22" y1="11" x2="16" y2="11"></line>
                    </svg>
                </div>
                <h3>Nenhum lead cadastrado ainda</h3>
                <p>
                    Sua base de leads está limpa. Cadastre o primeiro cliente em potencial para começar a gerenciar o pipeline de vendas.
                </p>
                <a href="/leads/new" role="button" class="primary">
                    + Cadastrar Primeiro Lead
                </a>
            </div>
        <?php else: ?>
            <!-- Estado Sem Resultados (No Results State): Filtros ativos não retornaram nada -->
            <div class="state-box">
                <div class="state-box-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                </div>
                <h3>Nenhum resultado encontrado</h3>
                <p>
                    Nenhum lead corresponde aos filtros ou termos pesquisados no momento. Tente buscar por outros termos ou redefinir os filtros.
                </p>
                <a href="/leads" role="button" class="outline secondary" hx-get="/leads" hx-target="#leads-table-container" hx-indicator="#leads-loading" hx-push-url="true">
                    Limpar Filtros de Busca
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Visualização 1: Tabela Operacional de Leads (Desktop / Telas Médias e Grandes >= 769px) -->
        <div class="leads-desktop-table" style="overflow-x: auto;">
            <table class="table-compact" role="grid" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th scope="col" style="min-width: 180px;">Lead / Cliente</th>
                        <th scope="col" style="min-width: 180px;">Contato</th>
                        <th scope="col">Estágio</th>
                        <th scope="col">Origem</th>
                        <th scope="col">Responsável</th>
                        <th scope="col">Próximo Retorno</th>
                        <th scope="col">Cadastro</th>
                        <th scope="col" style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <?php $cleanDigits = preg_replace('/\D+/', '', $lead['phone'] ?? '') ?? ''; ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="/leads/<?= $e((string)$lead['id']) ?>" style="text-decoration: none; color: inherit;">
                                        <?= $e($lead['name']) ?>
                                    </a>
                                </strong>
                                <small style="display: block; color: var(--pico-muted-color); font-size: 0.75rem;">
                                    ID #<?= $e((string)$lead['id']) ?>
                                </small>
                                <?php if (!empty($lead['notes'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--pico-muted-color); margin-top: 0.2rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e($lead['notes']) ?>">
                                        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                        <?= $e($lead['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($lead['phone'])): ?>
                                    <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                        <a href="tel:<?= $e($cleanDigits) ?>" title="Ligar para <?= $e($lead['name']) ?>" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            <?= $e($formatPhone($lead['phone'])) ?>
                                        </a>
                                        <a href="https://wa.me/55<?= $e($cleanDigits) ?>" target="_blank" rel="noopener noreferrer" title="Conversar no WhatsApp" class="btn-whatsapp" style="padding: 0.15rem 0.4rem; font-size: 0.75rem; border-radius: var(--pico-border-radius); text-decoration: none;">
                                            <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                            Zap
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($lead['email'])): ?>
                                    <div style="font-size: 0.8rem; color: var(--pico-muted-color); margin-top: 0.2rem;">
                                        <a href="mailto:<?= $e($lead['email']) ?>" title="Enviar e-mail para <?= $e($lead['email']) ?>" style="text-decoration: none; color: inherit; display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                            <?= $e($lead['email']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($lead['phone']) && empty($lead['email'])): ?>
                                    <span style="color: var(--pico-muted-color); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $e($getStageBadge($lead['stage'])) ?>">
                                    <?= $e($getStageLabel($lead['stage'])) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;">
                                    <?= $e(ucfirst($lead['source'] ?? 'manual')) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;">
                                    <?= $e($lead['assigned_user_name'] ?? 'Não atribuído') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($lead['next_follow_up_at'])): ?>
                                    <?php $followStatus = $getFollowUpStatus($lead['next_follow_up_at'], 'pending'); ?>
                                    <span class="badge <?= $e($getStatusBadgeClass($followStatus)) ?>" style="font-size: 0.7rem; margin-bottom: 0.2rem;">
                                        <?= $e($getStatusLabel($followStatus)) ?>
                                    </span>
                                    <strong style="display: block; font-size: 0.85rem; color: <?= $followStatus === 'overdue' ? '#dc2626' : 'inherit' ?>;">
                                        <?= $e($formatDate($lead['next_follow_up_at'])) ?>
                                    </strong>
                                    <small style="display: block; font-size: 0.75rem; color: var(--pico-muted-color);">
                                        <?= $e($humanDiff($lead['next_follow_up_at'], 'pending')) ?>
                                    </small>
                                <?php else: ?>
                                    <span style="font-size: 0.8rem; color: var(--pico-muted-color);">Sem agendamento</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; color: var(--pico-muted-color);">
                                    <?= $e($formatDate($lead['created_at'])) ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/leads/<?= $e((string)$lead['id']) ?>" role="button" class="outline" style="padding: 0.35rem 0.85rem; font-size: 0.85rem; min-height: 36px; display: inline-flex; align-items: center; margin: 0;">
                                    Ver Ficha
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Visualização 2: Cards Otimizados para Telas Menores e Touch (Mobile <= 768px) -->
        <div class="leads-mobile-list" aria-label="Lista de leads para celular">
            <?php foreach ($leads as $lead): ?>
                <?php $cleanDigits = preg_replace('/\D+/', '', $lead['phone'] ?? '') ?? ''; ?>
                <article class="lead-mobile-card">
                    <div class="lead-mobile-card-header">
                        <div>
                            <h4 class="lead-mobile-card-title">
                                <a href="/leads/<?= $e((string)$lead['id']) ?>">
                                    <?= $e($lead['name']) ?>
                                </a>
                            </h4>
                            <small style="color: var(--pico-muted-color); font-size: 0.75rem;">
                                ID #<?= $e((string)$lead['id']) ?> &bull; <?= $e(date('d/m/Y H:i', strtotime($lead['created_at']))) ?>
                            </small>
                            <?php if (!empty($lead['notes'])): ?>
                                <div style="font-size: 0.75rem; color: var(--pico-muted-color); margin-top: 0.25rem;">
                                    <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    <?= $e($lead['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= $e($getStageBadge($lead['stage'])) ?>">
                            <?= $e($getStageLabel($lead['stage'])) ?>
                        </span>
                    </div>

                    <div class="lead-mobile-card-meta">
                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <?= $e($lead['assigned_user_name'] ?? 'Não atribuído') ?>
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            <?= $e(ucfirst($lead['source'] ?? 'manual')) ?>
                        </span>
                    </div>

                    <?php if (!empty($lead['next_follow_up_at'])): ?>
                        <?php $mobStatus = $getFollowUpStatus($lead['next_follow_up_at'], 'pending'); ?>
                        <div style="margin-top: 0.5rem; padding: 0.35rem 0.5rem; background: var(--pico-background-color); border-radius: var(--pico-border-radius); font-size: 0.875rem;">
                            <strong>Retorno:</strong> <?= $e($formatDate($lead['next_follow_up_at'])) ?>
                            <span class="badge <?= $e($getStatusBadgeClass($mobStatus)) ?>" style="font-size: 0.75rem; margin-inline-start: 0.25rem;">
                                <?= $e($getStatusLabel($mobStatus)) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="lead-mobile-card-actions">
                        <?php if (!empty($cleanDigits)): ?>
                            <a href="https://wa.me/55<?= $e($cleanDigits) ?>" target="_blank" rel="noopener noreferrer" role="button" class="btn-whatsapp">
                                <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                WhatsApp
                            </a>
                            <a href="tel:<?= $e($cleanDigits) ?>" role="button" class="outline" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                Ligar
                            </a>
                        <?php else: ?>
                            <span style="font-size: 0.8rem; color: var(--pico-muted-color);">Sem telefone</span>
                            <span></span>
                        <?php endif; ?>

                        <a href="/leads/<?= $e((string)$lead['id']) ?>" role="button" class="contrast">
                            Ficha &rarr;
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Paginação no Servidor -->
        <nav class="pagination-nav" aria-label="Navegação de páginas de leads">
            <div style="font-size: 0.85rem; color: var(--pico-muted-color);">
                Mostrando <strong><?= $e((string)$startItem) ?></strong> a <strong><?= $e((string)$endItem) ?></strong> de <strong><?= $e((string)$total) ?></strong> leads
            </div>

            <?php if ($totalPages > 1): ?>
                <ul class="pagination-controls">
                    <!-- Botão Anterior -->
                    <?php if ($page > 1): ?>
                        <li>
                            <a href="/leads?<?= http_build_query(array_merge($filters, ['page' => $page - 1, 'per_page' => $perPage])) ?>"
                               hx-get="/leads?<?= http_build_query(array_merge($filters, ['page' => $page - 1, 'per_page' => $perPage])) ?>"
                               hx-target="#leads-table-container"
                               hx-indicator="#leads-loading"
                               hx-push-url="true"
                               title="Página anterior">
                                &laquo; Anterior
                            </a>
                        </li>
                    <?php else: ?>
                        <li><span class="is-disabled">&laquo; Anterior</span></li>
                    <?php endif; ?>

                    <!-- Números das Páginas -->
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <li><span class="is-active"><?= $e((string)$p) ?></span></li>
                        <?php elseif ($p === 1 || $p === $totalPages || abs($p - $page) <= 2): ?>
                            <li>
                                <a href="/leads?<?= http_build_query(array_merge($filters, ['page' => $p, 'per_page' => $perPage])) ?>"
                                   hx-get="/leads?<?= http_build_query(array_merge($filters, ['page' => $p, 'per_page' => $perPage])) ?>"
                                   hx-target="#leads-table-container"
                                   hx-indicator="#leads-loading"
                                   hx-push-url="true">
                                    <?= $e((string)$p) ?>
                                </a>
                            </li>
                        <?php elseif (abs($p - $page) === 3): ?>
                            <li><span>&hellip;</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <!-- Botão Próximo -->
                    <?php if ($page < $totalPages): ?>
                        <li>
                            <a href="/leads?<?= http_build_query(array_merge($filters, ['page' => $page + 1, 'per_page' => $perPage])) ?>"
                               hx-get="/leads?<?= http_build_query(array_merge($filters, ['page' => $page + 1, 'per_page' => $perPage])) ?>"
                               hx-target="#leads-table-container"
                               hx-indicator="#leads-loading"
                               hx-push-url="true"
                               title="Próxima página">
                                Próxima &raquo;
                            </a>
                        </li>
                    <?php else: ?>
                        <li><span class="is-disabled">Próxima &raquo;</span></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
