<?php $currentStatus=$statuses[$signboard['operational_status']] ?? $signboard['operational_status']; ?>
<header class="property-detail-header">
    <div><a href="/signboards">← Placas</a><h1><?= $signboard['property_id'] !== null ? $e($signboard['property_code'].' · '.$signboard['property_title']) : 'Placa sem imóvel' ?></h1><?php if($signboard['property_id'] !== null): ?><p><?= $e(implode(', ',array_filter([$signboard['address'],$signboard['neighborhood'],$signboard['city']]))) ?></p><?php endif ?></div>
    <div class="signboard-detail-actions"><?php if($signboard['removed_at']===null): ?><a href="/signboards/<?= $e((string)$signboard['id']) ?>/edit" role="button" class="outline">Editar cadastro</a><?php endif ?><form method="POST" action="/signboards/<?= $e((string)$signboard['id']) ?>/delete" onsubmit="return confirm('Excluir esta placa do quadro? O histórico será mantido para auditoria.');"><?= $csrfField ?><button type="submit" class="btn-danger">Excluir placa</button></form></div>
</header>

<?php if($actionError): ?><p class="property-form-errors" role="alert"><?= $e($actionError) ?></p><?php endif ?>

<section class="signboard-detail-status"><span class="signboard-status signboard-status-<?= $e($signboard['operational_status']) ?>"><?= $e($currentStatus) ?></span><span class="signboard-stage-pill">Etapa: <strong><?= $e(['a_fazer'=>'A fazer','pago'=>'Pago','pronto'=>'Pronto','disponivel'=>'Disponível','usadas'=>'Usadas'][$signboard['board_stage']] ?? $signboard['board_stage']) ?></strong></span><span>Responsável: <strong><?= $e($signboard['responsible_name']) ?></strong></span></section>

<?php if($signboard['property_id']===null && $signboard['board_stage']==='disponivel'): ?><p><a href="/signboards/<?= $e((string)$signboard['id']) ?>/assign" role="button">Colocar placa</a></p><?php elseif($signboard['property_id']===null): ?><p role="status">Quando a placa chegar a Disponível, use “Colocar placa” para escolher o imóvel de referência.</p><?php endif ?>

<section class="property-detail-grid signboard-facts">
    <article><h2>Instalada</h2><p><?= $signboard['installed_at']?$e(substr($signboard['installed_at'],0,10)):'Ainda não' ?></p></article>
    <article><h2>Retirada</h2><p><?= $signboard['removed_at']?$e(substr($signboard['removed_at'],0,10)):'Ainda não' ?></p></article>
    <article><h2>Material</h2><p><?= $e(['lona'=>'Lona','adesivo'=>'Adesivo','outro'=>'Outro'][$signboard['material']] ?? $signboard['material']) ?></p></article>
    <article><h2>Tamanho</h2><p><?= $signboard['height_cm'] !== null ? $e(rtrim(rtrim(number_format((float)$signboard['height_cm'],2,',','.'),'0'),',')) : '—' ?> × <?= $signboard['length_cm'] !== null ? $e(rtrim(rtrim(number_format((float)$signboard['length_cm'],2,',','.'),'0'),',')) : '—' ?> cm</p></article>
    <article><h2>Tipo</h2><p><?= $e(['compra'=>'Compra','aluguel'=>'Aluguel','compra_aluguel'=>'Compra e aluguel'][$signboard['deal_type']] ?? $signboard['deal_type']) ?></p></article>
</section>

<?php if($signboard['property_id']===null && $signboard['installed_at']===null && $signboard['removed_at']===null): ?>
<article class="signboard-action"><header><h2>Instalação pendente</h2></header><p>Escolha o imóvel pelo botão “Colocar placa” antes de confirmar a instalação.</p></article>
<?php elseif($signboard['installed_at']===null && $signboard['removed_at']===null): ?>
<article class="signboard-action"><header><h2>Confirmar instalação</h2></header><p>Informe quando a placa foi instalada e registre a confirmação.</p><form method="POST" action="/signboards/<?= $e((string)$signboard['id']) ?>/install"><?= $csrfField ?><div class="property-form-grid"><label>Data da instalação<input type="date" name="date" required max="<?= $e(date('Y-m-d')) ?>" value="<?= $e($actionKind==='install'?($actionInput['date']??date('Y-m-d')):date('Y-m-d')) ?>"></label><label>Confirmação<textarea name="notes" required maxlength="1000" rows="2" placeholder="Ex.: instalada na grade frontal"><?= $e($actionKind==='install'?($actionInput['notes']??''):'') ?></textarea></label></div><button type="submit">Confirmar instalação</button></form></article>
<?php elseif($signboard['removed_at']===null): ?>
<article class="signboard-action"><header><h2>Registrar retirada</h2></header><p>A retirada encerra o ciclo da placa. Depois de confirmada, ela não poderá ser reinstalada; o histórico continuará disponível para consulta.</p><form method="POST" action="/signboards/<?= $e((string)$signboard['id']) ?>/remove"><?= $csrfField ?><div class="property-form-grid"><label>Data da retirada<input type="date" name="date" required min="<?= $e(substr($signboard['installed_at'],0,10)) ?>" max="<?= $e(date('Y-m-d')) ?>" value="<?= $e($actionKind==='remove'?($actionInput['date']??date('Y-m-d')):date('Y-m-d')) ?>"></label><label>Confirmação<textarea name="notes" required maxlength="1000" rows="2" placeholder="Ex.: placa retirada e devolvida ao escritório"><?= $e($actionKind==='remove'?($actionInput['notes']??''):'') ?></textarea></label></div><button type="submit" class="secondary">Confirmar retirada</button></form></article>
<?php endif ?>

<section class="signboard-history"><h2>Histórico de movimentações</h2>
    <?php if(!$movements): ?><p class="properties-empty-state">Nenhuma movimentação registrada.</p><?php else: ?><ol><?php foreach($movements as $movement): ?><li><div><strong><?php $labels=['created'=>'Cadastro criado','updated'=>'Cadastro atualizado','installed'=>'Instalação confirmada','removed'=>'Retirada confirmada','stage_changed'=>'Etapa alterada','assigned'=>'Placa colocada no imóvel']; echo $e($labels[$movement['movement_type']]??$movement['movement_type']); ?></strong><small><?= $e(substr($movement['moved_at'],0,10)) ?> · <?= $e($movement['user_name']??'Usuário indisponível') ?></small></div><p><?= nl2br($e($movement['notes'])) ?></p></li><?php endforeach ?></ol><?php endif ?>
</section>
