<?php $currentStatus=$statuses[$signboard['operational_status']] ?? $signboard['operational_status']; ?>
<header class="property-detail-header">
    <div><a href="/signboards">← Placas</a><h1><?= $e($signboard['property_code']) ?> · <?= $e($signboard['property_title']) ?></h1><p><?= $e(implode(', ',array_filter([$signboard['address'],$signboard['neighborhood'],$signboard['city']]))) ?></p></div>
    <?php if($signboard['removed_at']===null): ?><a href="/signboards/<?= $e((string)$signboard['id']) ?>/edit" role="button" class="outline">Editar cadastro</a><?php endif ?>
</header>

<?php if($actionError): ?><p class="property-form-errors" role="alert"><?= $e($actionError) ?></p><?php endif ?>

<section class="signboard-detail-status"><span class="signboard-status signboard-status-<?= $e($signboard['operational_status']) ?>"><?= $e($currentStatus) ?></span><span><?= $e($signboard['location']) ?></span><span>Responsável: <strong><?= $e($signboard['responsible_name']) ?></strong></span></section>

<section class="property-detail-grid signboard-facts">
    <article><h2>Instalada</h2><p><?= $signboard['installed_at']?$e(substr($signboard['installed_at'],0,10)):'Ainda não' ?></p></article>
    <article><h2>Validade</h2><p><?= $signboard['expires_at']?$e(substr($signboard['expires_at'],0,10)):'Não definida' ?></p></article>
    <article><h2>Retirada</h2><p><?= $signboard['removed_at']?$e(substr($signboard['removed_at'],0,10)):'Ainda não' ?></p></article>
</section>

<?php if($signboard['notes']): ?><section><h2>Observações do cadastro</h2><p><?= nl2br($e($signboard['notes'])) ?></p></section><?php endif ?>

<?php if($signboard['installed_at']===null && $signboard['removed_at']===null): ?>
<article class="signboard-action"><header><h2>Confirmar instalação</h2></header><p>Informe quando a placa foi instalada e registre a confirmação.</p><form method="POST" action="/signboards/<?= $e((string)$signboard['id']) ?>/install"><?= $csrfField ?><div class="property-form-grid"><label>Data da instalação<input type="date" name="date" required max="<?= $e(date('Y-m-d')) ?>" value="<?= $e($actionKind==='install'?($actionInput['date']??date('Y-m-d')):date('Y-m-d')) ?>"></label><label>Confirmação<textarea name="notes" required maxlength="1000" rows="2" placeholder="Ex.: instalada na grade frontal"><?= $e($actionKind==='install'?($actionInput['notes']??''):'') ?></textarea></label></div><button type="submit">Confirmar instalação</button></form></article>
<?php elseif($signboard['removed_at']===null): ?>
<article class="signboard-action"><header><h2>Registrar retirada</h2></header><p>A retirada encerra o ciclo da placa. Depois de confirmada, ela não poderá ser reinstalada; o histórico continuará disponível para consulta.</p><form method="POST" action="/signboards/<?= $e((string)$signboard['id']) ?>/remove"><?= $csrfField ?><div class="property-form-grid"><label>Data da retirada<input type="date" name="date" required min="<?= $e(substr($signboard['installed_at'],0,10)) ?>" max="<?= $e(date('Y-m-d')) ?>" value="<?= $e($actionKind==='remove'?($actionInput['date']??date('Y-m-d')):date('Y-m-d')) ?>"></label><label>Confirmação<textarea name="notes" required maxlength="1000" rows="2" placeholder="Ex.: placa retirada e devolvida ao escritório"><?= $e($actionKind==='remove'?($actionInput['notes']??''):'') ?></textarea></label></div><button type="submit" class="secondary">Confirmar retirada</button></form></article>
<?php endif ?>

<section class="signboard-history"><h2>Histórico de movimentações</h2>
    <?php if(!$movements): ?><p class="properties-empty-state">Nenhuma movimentação registrada.</p><?php else: ?><ol><?php foreach($movements as $movement): ?><li><div><strong><?php $labels=['created'=>'Cadastro criado','updated'=>'Cadastro atualizado','installed'=>'Instalação confirmada','removed'=>'Retirada confirmada']; echo $e($labels[$movement['movement_type']]??$movement['movement_type']); ?></strong><small><?= $e(substr($movement['moved_at'],0,10)) ?> · <?= $e($movement['user_name']??'Usuário indisponível') ?></small></div><p><?= nl2br($e($movement['notes'])) ?></p></li><?php endforeach ?></ol><?php endif ?>
</section>
