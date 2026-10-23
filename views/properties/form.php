<?php
$errorOrder = ['code','address','neighborhood','city','google_maps_url','built_area','land_area','intention','sale_price','rent_price','aspiration','type','status','condominium_price','iptu_price','listing_url'];
$firstError = null;
foreach ($errorOrder as $field) { if (isset($errors[$field])) { $firstError = $field; break; } }
$value = static fn(string $key, mixed $fallback = ''): string => (string)($property[$key] ?? $fallback);
$money = static function(string $key) use ($value): string {
    $raw = trim(str_replace(["\u{00A0}", ' '], '', $value($key)));
    if ($raw === '') return '';
    $normalized = str_contains($raw, ',') ? str_replace(',', '.', str_replace('.', '', $raw)) : $raw;
    return is_numeric($normalized) ? number_format((float)$normalized, 2, ',', '.') : $raw;
};
$area = static function(string $key) use ($value): string {
    $raw = trim($value($key));
    return preg_match('/^\d+,\d+$/', $raw) ? str_replace(',', '.', $raw) : $raw;
};
$errorId = static fn(string $field): string => 'property-' . str_replace('_', '-', $field) . '-error';
$error = static fn(string $field): string => isset($errors[$field]) ? '<small class="field-error-text" id="'.$errorId($field).'" tabindex="-1">'.htmlspecialchars((string)$errors[$field], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</small>' : '';
?>
<header class="property-form-header">
    <a href="/properties">← Imóveis</a>
    <h1><?= $e($title) ?></h1>
    <p>Dados de identificação, localização e valores do imóvel.</p>
</header>

<?php if (!empty($errors['general']) || $firstError !== null): ?>
    <div class="property-form-errors" role="alert" tabindex="-1">
        <strong><?= $e($errors['general'] ?? 'Revise os campos indicados antes de salvar o imóvel.') ?></strong>
        <?php if (count($errors) > 1): ?><ul><?php foreach ($errors as $field => $message): ?><?php if ($field !== 'general'): ?><li><a href="#<?= $e($errorId((string)$field)) ?>"><?= $e($message) ?></a></li><?php endif ?><?php endforeach ?></ul><?php endif ?>
    </div>
<?php endif ?>

<form class="property-form" method="POST" action="<?= $e($action) ?>">
    <?= $csrfField ?>
    <fieldset class="property-form-group">
        <legend>Identificação</legend>
        <div class="property-field-grid">
            <label for="property-code">Código do imóvel
                <input id="property-code" name="code" maxlength="40" required value="<?= $e($value('code')) ?>" aria-invalid="<?= isset($errors['code']) ? 'true' : 'false' ?>" <?= $firstError === 'code' ? 'autofocus' : '' ?> <?= isset($errors['code']) ? 'aria-describedby="'.$e($errorId('code')).'"' : '' ?>>
                <small>Único; também identifica links e importações.</small><?= $error('code') ?>
            </label>
            <label for="property-type">Tipo de imóvel
                <select id="property-type" name="type" required aria-invalid="<?= isset($errors['type']) ? 'true' : 'false' ?>" <?= $firstError === 'type' ? 'autofocus' : '' ?> <?= isset($errors['type']) ? 'aria-describedby="'.$e($errorId('type')).'"' : '' ?>><?php foreach ($types as $key => $label): ?><option value="<?= $e($key) ?>" <?= ($property['type'] ?? 'apartamento') === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach ?></select><?= $error('type') ?>
            </label>
        </div>
        <label for="property-status">Status operacional
            <select id="property-status" name="status" required aria-invalid="<?= isset($errors['status']) ? 'true' : 'false' ?>" <?= $firstError === 'status' ? 'autofocus' : '' ?> <?= isset($errors['status']) ? 'aria-describedby="'.$e($errorId('status')).'"' : '' ?>><?php foreach ($statuses as $key => $label): ?><option value="<?= $e($key) ?>" <?= ($property['status'] ?? 'disponivel') === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach ?></select><?= $error('status') ?>
        </label>
    </fieldset>

    <fieldset class="property-form-group">
        <legend>Localização</legend>
        <label for="property-address">Endereço/logradouro
                <input id="property-address" name="address" maxlength="180" required value="<?= $e($value('address')) ?>" aria-invalid="<?= isset($errors['address']) ? 'true' : 'false' ?>" <?= $firstError === 'address' ? 'autofocus' : '' ?> <?= isset($errors['address']) ? 'aria-describedby="'.$e($errorId('address')).'"' : '' ?>><?= $error('address') ?>
        </label>
        <div class="property-field-grid">
            <label for="property-neighborhood">Bairro
                <input id="property-neighborhood" name="neighborhood" maxlength="120" required value="<?= $e($value('neighborhood')) ?>" aria-invalid="<?= isset($errors['neighborhood']) ? 'true' : 'false' ?>" <?= $firstError === 'neighborhood' ? 'autofocus' : '' ?> <?= isset($errors['neighborhood']) ? 'aria-describedby="'.$e($errorId('neighborhood')).'"' : '' ?>><?= $error('neighborhood') ?>
            </label>
            <label for="property-city">Cidade
                <input id="property-city" name="city" maxlength="120" required value="<?= $e($value('city')) ?>" aria-invalid="<?= isset($errors['city']) ? 'true' : 'false' ?>" <?= $firstError === 'city' ? 'autofocus' : '' ?> <?= isset($errors['city']) ? 'aria-describedby="'.$e($errorId('city')).'"' : '' ?>><?= $error('city') ?>
            </label>
        </div>
        <label for="property-google-maps">Link do Google Maps (opcional)
                <input id="property-google-maps" name="google_maps_url" type="url" inputmode="url" placeholder="https://maps.google.com/..." value="<?= $e($value('google_maps_url')) ?>" aria-invalid="<?= isset($errors['google_maps_url']) ? 'true' : 'false' ?>" <?= $firstError === 'google_maps_url' ? 'autofocus' : '' ?> <?= isset($errors['google_maps_url']) ? 'aria-describedby="'.$e($errorId('google_maps_url')).'"' : '' ?>>
            <small>Informe um link compartilhável do Google Maps se estiver disponível.</small><?= $error('google_maps_url') ?>
        </label>
    </fieldset>

    <fieldset class="property-form-group">
        <legend>Áreas</legend>
        <div class="property-field-grid">
            <label for="property-built-area">Área construída (m², opcional)
                <input id="property-built-area" name="built_area" type="number" min="0.01" step="0.01" value="<?= $e($area('built_area')) ?>" aria-invalid="<?= isset($errors['built_area']) ? 'true' : 'false' ?>" <?= $firstError === 'built_area' ? 'autofocus' : '' ?> <?= isset($errors['built_area']) ? 'aria-describedby="'.$e($errorId('built_area')).'"' : '' ?>><?= $error('built_area') ?>
            </label>
            <label for="property-land-area">Área do terreno (m²)
                <input id="property-land-area" name="land_area" type="number" min="0" step="0.01" value="<?= $e($area('land_area')) ?>" aria-invalid="<?= isset($errors['land_area']) ? 'true' : 'false' ?>" <?= $firstError === 'land_area' ? 'autofocus' : '' ?> <?= isset($errors['land_area']) ? 'aria-describedby="'.$e($errorId('land_area')).'"' : '' ?>><?= $error('land_area') ?>
            </label>
        </div>
    </fieldset>

    <fieldset class="property-form-group">
        <legend>Negociação</legend>
        <div class="property-field-grid">
            <label for="property-intention">Intenção
                <select id="property-intention" name="intention" required aria-invalid="<?= isset($errors['intention']) ? 'true' : 'false' ?>" <?= $firstError === 'intention' ? 'autofocus' : '' ?> <?= isset($errors['intention']) ? 'aria-describedby="'.$e($errorId('intention')).'"' : '' ?>><?php foreach ($intentions as $key => $label): ?><option value="<?= $e($key) ?>" <?= ($property['intention'] ?? 'venda') === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach ?></select><?= $error('intention') ?>
            </label>
            <label for="property-aspiration">Pretensão
                <select id="property-aspiration" name="aspiration" required aria-invalid="<?= isset($errors['aspiration']) ? 'true' : 'false' ?>" <?= $firstError === 'aspiration' ? 'autofocus' : '' ?> <?= isset($errors['aspiration']) ? 'aria-describedby="'.$e($errorId('aspiration')).'"' : '' ?>><?php foreach ($aspirations as $key => $label): ?><option value="<?= $e($key) ?>" <?= ($property['aspiration'] ?? 'residencial') === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach ?></select><?= $error('aspiration') ?>
            </label>
        </div>
        <div class="property-field-grid">
            <label for="property-sale-price">Valor de venda (R$)
                <input id="property-sale-price" name="sale_price" inputmode="decimal" placeholder="0,00" value="<?= $e($money('sale_price')) ?>" aria-invalid="<?= isset($errors['sale_price']) ? 'true' : 'false' ?>" <?= $firstError === 'sale_price' ? 'autofocus' : '' ?> <?= isset($errors['sale_price']) ? 'aria-describedby="'.$e($errorId('sale_price')).'"' : '' ?>><?= $error('sale_price') ?>
            </label>
            <label for="property-rent-price">Valor de locação (R$)
                <input id="property-rent-price" name="rent_price" inputmode="decimal" placeholder="0,00" value="<?= $e($money('rent_price')) ?>" aria-invalid="<?= isset($errors['rent_price']) ? 'true' : 'false' ?>" <?= $firstError === 'rent_price' ? 'autofocus' : '' ?> <?= isset($errors['rent_price']) ? 'aria-describedby="'.$e($errorId('rent_price')).'"' : '' ?>><?= $error('rent_price') ?>
            </label>
        </div>
        <small>Valores são opcionais. Informe um valor quando estiver disponível; use vírgula nos centavos.</small>
    </fieldset>

    <fieldset class="property-form-group">
        <legend>Encargos e divulgação</legend>
        <div class="property-field-grid">
            <label for="property-condominium-price">Preço do condomínio (R$)
                <input id="property-condominium-price" name="condominium_price" inputmode="decimal" placeholder="Opcional" value="<?= $e($money('condominium_price')) ?>" aria-invalid="<?= isset($errors['condominium_price']) ? 'true' : 'false' ?>" <?= $firstError === 'condominium_price' ? 'autofocus' : '' ?> <?= isset($errors['condominium_price']) ? 'aria-describedby="'.$e($errorId('condominium_price')).'"' : '' ?>><?= $error('condominium_price') ?>
            </label>
            <label for="property-iptu-price">Preço do IPTU (R$, opcional)
                <input id="property-iptu-price" name="iptu_price" inputmode="decimal" placeholder="0,00" value="<?= $e($money('iptu_price')) ?>" aria-invalid="<?= isset($errors['iptu_price']) ? 'true' : 'false' ?>" <?= $firstError === 'iptu_price' ? 'autofocus' : '' ?> <?= isset($errors['iptu_price']) ? 'aria-describedby="'.$e($errorId('iptu_price')).'"' : '' ?>>
                <?= $error('iptu_price') ?>
            </label>
        </div>
        <label for="property-listing-url">Link do anúncio
            <input id="property-listing-url" name="listing_url" type="url" inputmode="url" placeholder="https://..." value="<?= $e($value('listing_url')) ?>" aria-invalid="<?= isset($errors['listing_url']) ? 'true' : 'false' ?>" <?= $firstError === 'listing_url' ? 'autofocus' : '' ?> <?= isset($errors['listing_url']) ? 'aria-describedby="'.$e($errorId('listing_url')).'"' : '' ?>>
            <small>Se vazio, será preenchido com https://www.pinciara.com.br/imovel/<?= $e($value('code', '{{codigo_imovel}}')) ?>.</small><?= $error('listing_url') ?>
        </label>
    </fieldset>

    <fieldset class="property-form-group">
        <legend>Observações</legend>
        <label for="property-notes">Observações internas
            <textarea id="property-notes" name="notes" rows="4" maxlength="4000"><?= $e($value('notes')) ?></textarea>
        </label>
    </fieldset>
    <div class="property-form-actions"><button type="submit">Salvar imóvel</button><a href="/properties" role="button" class="secondary outline">Cancelar</a></div>
</form>
