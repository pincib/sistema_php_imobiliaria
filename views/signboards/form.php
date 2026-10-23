<?php
$errorOrder = ['property_id', 'location', 'responsible_user_id', 'expires_at', 'notes', 'confirmation_notes'];
$firstError = null;
foreach ($errorOrder as $field) { if (isset($errors[$field])) { $firstError = $field; break; } }
?>
<header class="property-form-header">
    <a href="/signboards">← Placas</a>
    <h1><?= $e($title) ?></h1>
    <p>Relacione a placa a um imóvel e registre o ponto, responsável e validade.</p>
</header>

<?php if (!empty($errors)): ?><section class="property-form-errors" role="alert" tabindex="-1"><strong>Revise os dados:</strong><ul><?php foreach ($errors as $message): ?><li><?= $e($message) ?></li><?php endforeach ?></ul></section><?php endif ?>

<form class="property-form" method="POST" action="<?= $e($action) ?>">
    <?= $csrfField ?>
    <div class="property-form-grid">
        <label for="signboard-property">Imóvel
            <select id="signboard-property" name="property_id" required <?= isset($errors['property_id'])?'aria-invalid="true" aria-describedby="signboard-property-error"':'' ?> <?= $firstError==='property_id'?'autofocus':'' ?>>
                <option value="">Selecione um imóvel</option>
                <?php foreach($properties as $property): ?><option value="<?= $e((string)$property['id']) ?>" <?= (string)($signboard['property_id']??'')===(string)$property['id']?'selected':'' ?>><?= $e($property['code'].' · '.$property['title'].' — '.implode(', ',array_filter([$property['neighborhood'],$property['city']]))) ?></option><?php endforeach ?>
            </select>
            <?php if(isset($errors['property_id'])): ?><small class="field-error-text" id="signboard-property-error"><?= $e($errors['property_id']) ?></small><?php endif ?>
        </label>
        <label for="signboard-location">Ponto / localização no imóvel
            <input id="signboard-location" name="location" maxlength="250" required value="<?= $e($signboard['location']??'') ?>" placeholder="Ex.: grade frontal, lado esquerdo" <?= isset($errors['location'])?'aria-invalid="true" aria-describedby="signboard-location-error"':'' ?> <?= $firstError==='location'?'autofocus':'' ?>>
            <?php if(isset($errors['location'])): ?><small class="field-error-text" id="signboard-location-error"><?= $e($errors['location']) ?></small><?php endif ?>
        </label>
        <label for="signboard-responsible">Responsável
            <select id="signboard-responsible" name="responsible_user_id" required <?= isset($errors['responsible_user_id'])?'aria-invalid="true" aria-describedby="signboard-responsible-error"':'' ?> <?= $firstError==='responsible_user_id'?'autofocus':'' ?>>
                <option value="">Selecione uma pessoa</option>
                <?php foreach($users as $user): ?><option value="<?= $e((string)$user['id']) ?>" <?= (string)($signboard['responsible_user_id']??'')===(string)$user['id']?'selected':'' ?>><?= $e($user['name']) ?></option><?php endforeach ?>
            </select>
            <?php if(isset($errors['responsible_user_id'])): ?><small class="field-error-text" id="signboard-responsible-error"><?= $e($errors['responsible_user_id']) ?></small><?php endif ?>
        </label>
        <label for="signboard-expiration">Validade <small>(opcional)</small>
            <input id="signboard-expiration" name="expires_at" type="date" value="<?= $e(substr((string)($signboard['expires_at']??''),0,10)) ?>" <?= isset($errors['expires_at'])?'aria-invalid="true" aria-describedby="signboard-expiration-error"':'' ?> <?= $firstError==='expires_at'?'autofocus':'' ?>>
            <small>Depois dessa data, a situação muda automaticamente para vencida.</small>
            <?php if(isset($errors['expires_at'])): ?><small class="field-error-text" id="signboard-expiration-error"><?= $e($errors['expires_at']) ?></small><?php endif ?>
        </label>
        <label for="signboard-notes" class="property-form-wide">Observações
            <textarea id="signboard-notes" name="notes" rows="4" maxlength="2000" <?= isset($errors['notes'])?'aria-invalid="true" aria-describedby="signboard-notes-error"':'' ?> <?= $firstError==='notes'?'autofocus':'' ?>><?= $e($signboard['notes']??'') ?></textarea>
            <?php if(isset($errors['notes'])): ?><small class="field-error-text" id="signboard-notes-error"><?= $e($errors['notes']) ?></small><?php endif ?>
        </label>
        <?php if(!$editing): ?><label for="signboard-confirmation" class="property-form-wide">Observação do cadastro <small>(opcional)</small>
            <textarea id="signboard-confirmation" name="confirmation_notes" rows="2" maxlength="500" placeholder="Ex.: placa recebida e ponto de instalação combinado" <?= isset($errors['confirmation_notes'])?'aria-invalid="true" aria-describedby="signboard-confirmation-error"':'' ?> <?= $firstError==='confirmation_notes'?'autofocus':'' ?>><?= $e($signboard['confirmation_notes']??'') ?></textarea>
            <?php if(isset($errors['confirmation_notes'])): ?><small class="field-error-text" id="signboard-confirmation-error"><?= $e($errors['confirmation_notes']) ?></small><?php endif ?>
            <small>Se informada, a observação ficará no histórico. Sem texto, o histórico registra apenas o cadastro e seu autor.</small>
        </label><?php endif ?>
    </div>
    <?php if(!$properties): ?><p role="status">Cadastre um imóvel antes de cadastrar placas.</p><?php endif ?>
    <?php if(!$users): ?><p role="status">Cadastre ou reative um usuário antes de atribuir a responsabilidade por uma placa.</p><?php endif ?>
    <div class="property-form-actions"><button type="submit" <?= !$properties || !$users?'disabled':'' ?>><?= $editing?'Salvar alterações':'Cadastrar placa' ?></button><a href="/signboards" role="button" class="secondary outline">Cancelar</a></div>
</form>
