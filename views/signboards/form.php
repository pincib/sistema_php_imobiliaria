<?php
$errorOrder = ['responsible_user_id', 'material', 'height_cm', 'length_cm', 'deal_type', 'confirmation_notes'];
$firstError = null;
foreach ($errorOrder as $field) { if (isset($errors[$field])) { $firstError = $field; break; } }
?>
<header class="property-form-header">
    <a href="/signboards">← Placas</a>
    <h1><?= $e($title) ?></h1>
    <p>Cadastre a placa e acompanhe cada etapa no quadro. O imóvel será escolhido quando ela estiver disponível.</p>
</header>

<?php if (!empty($errors)): ?><section class="property-form-errors" role="alert" tabindex="-1"><strong>Revise os dados:</strong><ul><?php foreach ($errors as $message): ?><li><?= $e($message) ?></li><?php endforeach ?></ul></section><?php endif ?>

<form class="property-form" method="POST" action="<?= $e($action) ?>">
    <?= $csrfField ?>
    <div class="property-form-grid">
        <label for="signboard-responsible">Responsável
            <select id="signboard-responsible" name="responsible_user_id" required <?= isset($errors['responsible_user_id'])?'aria-invalid="true" aria-describedby="signboard-responsible-error"':'' ?> <?= $firstError==='responsible_user_id'?'autofocus':'' ?>>
                <option value="">Selecione uma pessoa</option>
                <?php foreach($users as $user): ?><option value="<?= $e((string)$user['id']) ?>" <?= (string)($signboard['responsible_user_id']??'')===(string)$user['id']?'selected':'' ?>><?= $e($user['name']) ?></option><?php endforeach ?>
            </select>
            <?php if(isset($errors['responsible_user_id'])): ?><small class="field-error-text" id="signboard-responsible-error"><?= $e($errors['responsible_user_id']) ?></small><?php endif ?>
        </label>
        <label for="signboard-material">Material
            <select id="signboard-material" name="material" required <?= isset($errors['material'])?'aria-invalid="true" aria-describedby="signboard-material-error"':'' ?> <?= $firstError==='material'?'autofocus':'' ?>>
                <?php foreach($materials as $key=>$label): ?><option value="<?= $e($key) ?>" <?= ($signboard['material']??'outro')===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach ?>
            </select>
            <?php if(isset($errors['material'])): ?><small class="field-error-text" id="signboard-material-error"><?= $e($errors['material']) ?></small><?php endif ?>
        </label>
        <fieldset class="signboard-size-fieldset <?= $firstError==='height_cm' || $firstError==='length_cm'?'property-form-wide':'' ?>">
            <legend>Tamanho <small>(cm)</small></legend>
            <div class="signboard-size-fields">
                <label for="signboard-height">Altura
                    <input id="signboard-height" name="height_cm" type="number" min="0.01" max="100000" step="0.01" required value="<?= $e((string)($signboard['height_cm']??'')) ?>" <?= isset($errors['height_cm'])?'aria-invalid="true" aria-describedby="signboard-height-error"':'' ?> <?= $firstError==='height_cm'?'autofocus':'' ?>>
                    <?php if(isset($errors['height_cm'])): ?><small class="field-error-text" id="signboard-height-error"><?= $e($errors['height_cm']) ?></small><?php endif ?>
                </label>
                <label for="signboard-length">Comprimento
                    <input id="signboard-length" name="length_cm" type="number" min="0.01" max="100000" step="0.01" required value="<?= $e((string)($signboard['length_cm']??'')) ?>" <?= isset($errors['length_cm'])?'aria-invalid="true" aria-describedby="signboard-length-error"':'' ?> <?= $firstError==='length_cm'?'autofocus':'' ?>>
                    <?php if(isset($errors['length_cm'])): ?><small class="field-error-text" id="signboard-length-error"><?= $e($errors['length_cm']) ?></small><?php endif ?>
                </label>
            </div>
        </fieldset>
        <label for="signboard-deal-type">Tipo
            <select id="signboard-deal-type" name="deal_type" required <?= isset($errors['deal_type'])?'aria-invalid="true" aria-describedby="signboard-deal-type-error"':'' ?> <?= $firstError==='deal_type'?'autofocus':'' ?>>
                <?php foreach($dealTypes as $key=>$label): ?><option value="<?= $e($key) ?>" <?= ($signboard['deal_type']??'compra')===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach ?>
            </select>
            <?php if(isset($errors['deal_type'])): ?><small class="field-error-text" id="signboard-deal-type-error"><?= $e($errors['deal_type']) ?></small><?php endif ?>
        </label>
        <?php if(!$editing): ?><label for="signboard-confirmation" class="property-form-wide">Confirmação do cadastro <small>(opcional)</small>
            <textarea id="signboard-confirmation" name="confirmation_notes" rows="2" maxlength="500" placeholder="Ex.: placa recebida e ponto de instalação combinado" <?= isset($errors['confirmation_notes'])?'aria-invalid="true" aria-describedby="signboard-confirmation-error"':'' ?> <?= $firstError==='confirmation_notes'?'autofocus':'' ?>><?= $e($signboard['confirmation_notes']??'') ?></textarea>
            <?php if(isset($errors['confirmation_notes'])): ?><small class="field-error-text" id="signboard-confirmation-error"><?= $e($errors['confirmation_notes']) ?></small><?php endif ?>
            <small>Se informada, a observação ficará no histórico. Sem texto, o histórico registra apenas o cadastro e seu autor.</small>
        </label><?php endif ?>
    </div>
    <?php if(!$users): ?><p role="status">Cadastre ou reative um usuário antes de atribuir a responsabilidade por uma placa.</p><?php endif ?>
    <div class="property-form-actions"><button type="submit" <?= !$users?'disabled':'' ?>><?= $editing?'Salvar alterações':'Cadastrar placa' ?></button><a href="/signboards" role="button" class="secondary outline">Cancelar</a></div>
</form>
