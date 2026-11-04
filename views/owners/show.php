<?php
/**
 * Plataforma Pinciara — Ficha Unificada do Proprietário (Apple Pro Edition)
 * Central Integrada: Cadastro, Verificação de 30 Dias e Disparo WhatsApp em 1 Tela
 *
 * @var array<string, mixed> $owner
 * @var list<array<string, mixed>> $properties
 * @var list<array<string, mixed>> $available
 * @var list<array<string, mixed>> $whatsappHistory
 * @var string $defaultMessage
 * @var string $whatsappUrl
 * @var string $whatsappRule
 * @var string $cleanPhone
 * @var callable $e
 * @var callable $formatPhone
 * @var string $csrfField
 */
?>

<!-- Cabeçalho Principal -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle);">
    <div>
        <a href="/owners" style="font-size: 0.85rem; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 0.4rem;">
            &larr; Voltar para Proprietários
        </a>
        <h1 style="margin: 0 0 0.35rem 0; font-size: 1.85rem; font-weight: 700; letter-spacing: -0.025em;">
            <?= $e($owner['name']) ?>
        </h1>
        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">
            Proprietário ID #<?= (int)$owner['id'] ?>
            <?php if (!empty($owner['phone'])): ?>
                &bull; Tel: <strong><?= $e($formatPhone($owner['phone'])) ?></strong>
            <?php endif; ?>
            <?php if (!empty($owner['email'])): ?>
                &bull; <?= $e($owner['email']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="/owners/<?= (int)$owner['id'] ?>/edit" role="button" class="outline secondary" style="font-size: 0.85rem;">
            Editar Dados
        </a>
        <form method="post" action="/contacts/owners/<?= (int)$owner['id'] ?>/invalid" style="margin: 0; display: inline;">
            <?= $csrfField ?>
            <input type="hidden" name="invalid_contact" value="<?= $owner['invalid_contact'] ? '0' : '1' ?>">
            <input type="hidden" name="return_to" value="/owners/<?= (int)$owner['id'] ?>">
            <button class="outline secondary" type="submit" style="font-size: 0.78rem; padding: 0.45rem 0.75rem; <?= $owner['invalid_contact'] ? 'color: var(--color-danger); border-color: var(--color-danger);' : '' ?>">
                <?= $owner['invalid_contact'] ? '⚠️ Contato Inválido (Remover)' : 'Marcar Inválido' ?>
            </button>
        </form>
    </div>
</div>

<!-- =======================================================================
     1. CARD DE STATUS DE ATUALIZAÇÃO (Critério dos 30 Dias)
     ======================================================================= -->
<?php if ($owner['is_updated']): ?>
    <div style="background: var(--color-success-subtle); border: 1px solid var(--color-success-border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <span class="badge badge-completed">✓ Cadastro Atualizado</span>
                <strong style="color: var(--color-success-text); font-size: 0.95rem;">
                    Válido por mais <?= (int)$owner['days_remaining'] ?> dia(s) (até <?= $e($owner['expires_at']) ?>)
                </strong>
            </div>
            <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);">
                Última confirmação realizada em <strong><?= $e($owner['verified_at_formatted']) ?></strong>
                <?= !empty($owner['verified_by_name']) ? ' por <strong>' . $e($owner['verified_by_name']) . '</strong>' : '' ?>.
            </p>
        </div>
        <form action="/owners/<?= (int)$owner['id'] ?>/verify" method="POST" style="margin: 0;">
            <?= $csrfField ?>
            <input type="hidden" name="return_to" value="/owners/<?= (int)$owner['id'] ?>">
            <button type="submit" class="outline secondary" style="padding: 0.45rem 0.9rem; font-size: 0.825rem; font-weight: 600;">
                🔄 Renovar Validade (+30 dias)
            </button>
        </form>
    </div>
<?php else: ?>
    <div style="background: var(--color-warning-subtle); border: 1px solid var(--color-warning-border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <span class="badge badge-overdue">⚠️ Cadastro Desatualizado</span>
                <strong style="color: var(--color-warning-text); font-size: 0.95rem;">
                    <?= $owner['verified_days_ago'] !== null ? 'Sem atualização há ' . (int)$owner['verified_days_ago'] . ' dias (passou de 30 dias)' : 'Este proprietário ainda não possui verificação recente' ?>
                </strong>
            </div>
            <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);">
                Envie o WhatsApp abaixo ou confirme o contato para renovar a validade por mais 30 dias.
            </p>
        </div>
        <form action="/owners/<?= (int)$owner['id'] ?>/verify" method="POST" style="margin: 0;">
            <?= $csrfField ?>
            <input type="hidden" name="return_to" value="/owners/<?= (int)$owner['id'] ?>">
            <button type="submit" class="outline secondary" style="padding: 0.45rem 0.9rem; font-size: 0.825rem;" title="Marcar como atualizado caso já tenha falado por telefone ou pessoalmente">
                📞 Validar sem WhatsApp (+30 dias)
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- =======================================================================
     2. CENTRAL UNIFICADA DE DISPARO WHATSAPP (Direto na Tela!)
     ======================================================================= -->
<article style="padding: 1.5rem; margin-bottom: 2.5rem; border-color: var(--border-subtle); box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
        <div>
            <h3 style="margin: 0 0 0.25rem 0; font-size: 1.15rem; display: flex; align-items: center; gap: 0.5rem;">
                <span>💬 Mensagem de Atualização Mensal</span>
            </h3>
            <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);">
                Texto pré-formatado com os imóveis deste proprietário. Você pode editar se quiser.
            </p>
        </div>
        <?php if (!empty($cleanPhone)): ?>
            <span class="badge badge-neutral" style="font-size: 0.75rem;">
                Destinatário: <?= $e($formatPhone($owner['phone'])) ?>
            </span>
        <?php else: ?>
            <span class="badge badge-overdue" style="font-size: 0.75rem;">
                Cadastre um telefone para abrir o WhatsApp
            </span>
        <?php endif; ?>
    </div>

    <!-- Área de Edição da Mensagem -->
    <div style="margin-bottom: 1.25rem;">
        <textarea id="unified-whatsapp-message" 
                  rows="4" 
                  style="font-size: 0.9rem; line-height: 1.45; margin: 0; padding: 0.85rem; border-color: var(--border-strong);"
                  placeholder="Mensagem de contato com o proprietário..."><?= $e($defaultMessage) ?></textarea>
    </div>

    <!-- Barra de Ações Rápidas em 1 Clique -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-subtle);">
        <div>
            <?php if (!empty($cleanPhone)): ?>
                <a id="btn-open-whatsapp" 
                   href="<?= $e($whatsappUrl) ?>" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="btn-whatsapp" 
                   style="padding: 0.6rem 1.25rem; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                    💬 1. Abrir no WhatsApp
                </a>
            <?php else: ?>
                <button type="button" class="btn outline" disabled style="opacity: 0.6;">
                    Telefone não cadastrado
                </button>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <!-- Formulário que confirma e já renova por 30 dias em 1 clique -->
            <form method="post" action="/owners/<?= (int)$owner['id'] ?>/whatsapp" id="form-confirm-whatsapp" style="margin: 0;">
                <?= $csrfField ?>
                <input type="hidden" name="rule" value="<?= $e($whatsappRule) ?>">
                <input type="hidden" name="result" value="confirmed">
                <input type="hidden" name="message" id="input-confirm-message" value="<?= $e($defaultMessage) ?>">
                <button type="submit" 
                        class="primary" 
                        style="padding: 0.6rem 1.25rem; font-size: 0.9rem; font-weight: 600;"
                        onclick="document.getElementById('input-confirm-message').value = document.getElementById('unified-whatsapp-message').value;">
                    ✓ 2. Confirmar Envio e Atualizar (+30 dias)
                </button>
            </form>
        </div>
    </div>
</article>

<script>
    // Sincroniza o link do WhatsApp caso o corretor edite a mensagem no textarea
    const msgInput = document.getElementById('unified-whatsapp-message');
    const waLink = document.getElementById('btn-open-whatsapp');
    const cleanPhone = <?= json_encode($cleanPhone) ?>;

    if (msgInput && waLink && cleanPhone) {
        msgInput.addEventListener('input', function() {
            const intl = cleanPhone.startsWith('55') ? cleanPhone : '55' + cleanPhone;
            waLink.href = 'https://wa.me/' + intl + '?text=' + encodeURIComponent(this.value);
            document.getElementById('input-confirm-message').value = this.value;
        });
    }
</script>

<!-- =======================================================================
     3. HISTÓRICO DE MENSAGENS E DADOS CADASTRAIS
     ======================================================================= -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    <!-- Histórico Recente de WhatsApp -->
    <article style="padding: 1.5rem;">
        <h3 style="font-size: 1.05rem; margin-bottom: 0.75rem;">Histórico Recente de WhatsApp</h3>
        <?php if (!empty($whatsappHistory)): ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 250px; overflow-y: auto;">
                <?php foreach ($whatsappHistory as $log): ?>
                    <div style="padding: 0.65rem 0.85rem; background: var(--bg-muted); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); font-size: 0.825rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <strong><?= $e($log['rule']) ?></strong>
                            <span class="badge <?= $log['result'] === 'confirmado pelo corretor' ? 'badge-completed' : 'badge-neutral' ?>" style="font-size: 0.68rem;">
                                <?= $e($log['result']) ?>
                            </span>
                        </div>
                        <p style="margin: 0 0 0.25rem 0; color: var(--text-secondary); line-height: 1.35;">
                            <?= nl2br($e($log['message'])) ?>
                        </p>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">
                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?> &bull; <?= $e($log['user_name'] ?? 'Corretor') ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">
                Nenhum contato registrado no histórico ainda.
            </p>
        <?php endif; ?>
    </article>

    <!-- Observações Cadastrais -->
    <article style="padding: 1.5rem;">
        <h3 style="font-size: 1.05rem; margin-bottom: 0.75rem;">Anotações do Proprietário</h3>
        <p style="margin: 0; font-size: 0.88rem; line-height: 1.5; color: var(--text-main); white-space: pre-wrap;">
            <?= $owner['notes'] ? nl2br($e($owner['notes'])) : '<em style="color: var(--text-muted);">Nenhuma anotação adicional registrada.</em>' ?>
        </p>
    </article>
</div>

<!-- =======================================================================
     4. IMÓVEIS VINCULADOS
     ======================================================================= -->
<section style="margin-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h2 style="font-size: 1.25rem; margin: 0;">
                Imóveis Vinculados <small style="color: var(--text-muted);">(<?= count($properties) ?>)</small>
            </h2>
        </div>
    </div>

    <?php if ($properties): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th scope="col" style="min-width: 100px;">Código</th>
                        <th scope="col" style="min-width: 240px;">Imóvel</th>
                        <th scope="col" style="min-width: 180px;">Localização</th>
                        <th scope="col" style="min-width: 120px;">Status</th>
                        <th scope="col" style="text-align: right; min-width: 140px;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $p): ?>
                        <tr>
                            <td>
                                <a href="/properties/<?= (int)$p['id'] ?>" class="badge badge-neutral" style="font-weight: 700; font-size: 0.85rem; text-decoration: none;">
                                    🏠 <?= $e($p['code']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="/properties/<?= (int)$p['id'] ?>" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                                    <?= $e($p['title']) ?>
                                </a>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <?= $e(trim(($p['neighborhood'] ?? '') . ' — ' . ($p['city'] ?? ''), ' —') ?: '—') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-neutral" style="font-size: 0.72rem; text-transform: capitalize;">
                                    <?= $e($p['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <form method="post" action="/owners/<?= (int)$owner['id'] ?>/properties/<?= (int)$p['id'] ?>/delete" onsubmit="return confirm('Remover o vínculo do imóvel <?= $e($p['code']) ?> com este proprietário?')" style="margin: 0; display: inline;">
                                    <?= $csrfField ?>
                                    <button class="outline secondary owner-unlink" type="submit" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; color: var(--color-danger);">
                                        Desvincular
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state-box" style="padding: 2.5rem 1rem; margin-bottom: 1.5rem;">
            <p style="margin: 0; font-size: 0.9rem; color: var(--text-muted);">
                Nenhum imóvel vinculado a este proprietário até o momento.
            </p>
        </div>
    <?php endif; ?>

    <!-- Formulário para Vincular Novo Imóvel -->
    <article class="filters-toolbar" style="padding: 1.25rem 1.5rem; margin-top: 1.5rem;">
        <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem;">Vincular outro imóvel pelo código</h4>
        <form method="post" action="/owners/<?= (int)$owner['id'] ?>/properties" style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <?= $csrfField ?>
            <div style="flex: 1; min-width: 250px;">
                <label for="property-code" style="font-size: 0.8rem; margin-bottom: 0.25rem;">
                    Digite o código do imóvel
                </label>
                <input list="owner-property-codes" 
                       id="property-code" 
                       name="property_code" 
                       required 
                       placeholder="Ex.: AP102 ou CA304" 
                       autocomplete="off"
                       style="margin: 0;">
                <datalist id="owner-property-codes">
                    <?php foreach ($available as $p): ?>
                        <option value="<?= $e($p['code']) ?>"><?= $e($p['title']) ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <button type="submit" class="primary" style="margin: 0; padding: 0.55rem 1rem;">
                + Vincular Imóvel
            </button>
        </form>
    </article>
</section>
