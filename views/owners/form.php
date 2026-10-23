<div class="property-form-header">
    <a href="/owners">← Voltar para proprietários</a>
    <h1><?= $e($title) ?></h1>
    <p>Contato e imóveis relacionados ao proprietário.</p>
</div>
<?php if ($errors): ?>
    <div class="property-form-errors" role="alert">
        <strong>Revise os campos abaixo.</strong>
        <ul><?php foreach ($errors as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php
$selected = array_map('intval', (array)($owner['property_ids'] ?? []));
if (isset($owner['id']) && !array_key_exists('property_ids', $owner)) {
    $st = \App\Database\Connection::getPdo()->prepare('SELECT property_id FROM owner_properties WHERE owner_id=:id');
    $st->execute([':id' => $owner['id']]);
    $selected = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}
$fieldError = static fn(string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . '-error"' : ' aria-invalid="false"';
?>
<form method="post" action="<?= $e($action) ?>" class="owner-form">
    <?= $csrfField ?>
    <?php if (isset($errors['general'])): ?><p class="field-error-text" role="alert"><?= $e($errors['general']) ?></p><?php endif; ?>
    <label for="owner-name">Nome <span aria-hidden="true">*</span>
        <input id="owner-name" name="name" required maxlength="150" value="<?= $e($owner['name'] ?? '') ?>" autocomplete="name" autofocus<?= $fieldError('name') ?>>
        <?php if (isset($errors['name'])): ?><small id="name-error" class="field-error-text" role="alert"><?= $e($errors['name']) ?></small><?php endif; ?>
    </label>
    <div class="property-field-grid">
        <label for="owner-phone">Telefone
            <input type="tel" id="owner-phone" name="phone" value="<?= $e($owner['phone'] ?? '') ?>" placeholder="(21) 99999-8888" autocomplete="tel" maxlength="30"<?= $fieldError('phone') ?>>
            <?php if (isset($errors['phone'])): ?><small id="phone-error" class="field-error-text" role="alert"><?= $e($errors['phone']) ?></small><?php endif; ?>
        </label>
        <label for="owner-email">E-mail
            <input type="email" id="owner-email" name="email" value="<?= $e($owner['email'] ?? '') ?>" maxlength="150" autocomplete="email"<?= $fieldError('email') ?>>
            <?php if (isset($errors['email'])): ?><small id="email-error" class="field-error-text" role="alert"><?= $e($errors['email']) ?></small><?php endif; ?>
        </label>
    </div>
    <fieldset class="owner-property-picker" aria-describedby="owner-properties-help<?= isset($errors['property_ids']) ? ' owner-properties-error' : '' ?>">
        <legend>Imóveis vinculados</legend>
        <label for="owner-property-search" hidden>Buscar imóvel por código ou título
            <input id="owner-property-search" type="search" autocomplete="off" placeholder="Digite um código ou nome">
        </label>
        <p id="owner-properties-help">Opcional. Selecione os imóveis agora ou associe-os depois pelo código. Os vínculos selecionados ficam visíveis abaixo, mesmo durante a busca.</p>
        <p class="owner-property-count" id="owner-property-count" aria-live="polite"><?= count($selected) ?> selecionado(s) · <?= count($properties) ?> disponível(is)</p>
        <ul class="owner-selected-properties" id="owner-selected-properties" aria-label="Imóveis selecionados"></ul>
        <div class="owner-property-options" id="owner-property-options">
            <?php foreach ($properties as $property): ?>
                <label class="owner-property-option">
                    <input type="checkbox" name="property_ids[]" value="<?= (int)$property['id'] ?>" <?= in_array((int)$property['id'], $selected, true) ? 'checked' : '' ?> aria-invalid="<?= isset($errors['property_ids']) ? 'true' : 'false' ?>" <?= isset($errors['property_ids']) ? 'aria-describedby="owner-properties-error"' : '' ?>>
                    <span><strong><?= $e($property['code']) ?></strong> — <?= $e($property['title']) ?></span>
                </label>
            <?php endforeach; ?>
            <?php if (!$properties): ?><p class="properties-empty-state">Nenhum imóvel cadastrado para vincular.</p><?php endif; ?>
        </div>
        <p id="owner-property-no-results" role="status" hidden>Nenhum imóvel corresponde à busca.</p>
        <?php if (isset($errors['property_ids'])): ?><small class="field-error-text" id="owner-properties-error" role="alert"><?= $e($errors['property_ids']) ?></small><?php endif; ?>
    </fieldset>
    <label for="owner-notes">Observações
        <textarea id="owner-notes" name="notes" rows="4" maxlength="4000" aria-describedby="owner-notes-help<?= isset($errors['notes']) ? ' notes-error' : '' ?>" aria-invalid="<?= isset($errors['notes']) ? 'true' : 'false' ?>"><?= $e($owner['notes'] ?? '') ?></textarea>
        <small id="owner-notes-help">Até 4.000 caracteres.</small>
        <?php if (isset($errors['notes'])): ?><small id="notes-error" class="field-error-text" role="alert"><?= $e($errors['notes']) ?></small><?php endif; ?>
    </label>
    <div class="property-form-actions">
        <button type="submit">Salvar proprietário</button>
        <a href="<?= isset($owner['id']) ? '/owners/' . (int)$owner['id'] : '/owners' ?>" role="button" class="outline secondary">Cancelar</a>
    </div>
</form>
<script>
(()=>{const search=document.getElementById('owner-property-search'),searchLabel=search?.closest('label'),list=document.getElementById('owner-property-options'),count=document.getElementById('owner-property-count'),selectedList=document.getElementById('owner-selected-properties'),noResults=document.getElementById('owner-property-no-results');if(!search||!searchLabel||!list||!count||!selectedList||!noResults)return;searchLabel.hidden=false;const options=[...list.querySelectorAll('.owner-property-option')];function update(){const query=search.value.trim().toLocaleLowerCase('pt-BR');let visible=0,selected=0;selectedList.replaceChildren();for(const option of options){const input=option.querySelector('input'),matches=option.textContent.toLocaleLowerCase('pt-BR').includes(query);option.hidden=!matches;if(matches)visible++;if(input.checked){selected++;const item=document.createElement('li');item.textContent=option.querySelector('span').textContent.trim();selectedList.append(item);}}count.textContent=selected+' selecionado(s) · '+visible+' resultado(s)';noResults.hidden=visible>0;}search.addEventListener('input',update);list.addEventListener('change',update);update();})();
</script>
