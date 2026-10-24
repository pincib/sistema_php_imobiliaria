<header class="property-form-header">
    <a href="/signboards/<?= $e((string)$signboard['id']) ?>">← Voltar para a placa</a>
    <h1>Colocar placa</h1>
    <p>Escolha o imóvel onde esta placa será usada. Ao confirmar, ela será movida para Usadas.</p>
</header>

<section class="signboard-detail-status" aria-label="Dados da placa">
    <span>Material: <strong><?= $e(['lona'=>'Lona','adesivo'=>'Adesivo','outro'=>'Outro'][$signboard['material']] ?? $signboard['material']) ?></strong></span>
    <span>Tamanho: <strong><?= $signboard['height_cm'] !== null ? $e(rtrim(rtrim(number_format((float)$signboard['height_cm'],2,',','.'),'0'),',')) : '—' ?> × <?= $signboard['length_cm'] !== null ? $e(rtrim(rtrim(number_format((float)$signboard['length_cm'],2,',','.'),'0'),',')) : '—' ?> cm</strong></span>
</section>

<?php if (!empty($errors)): ?><section class="property-form-errors" role="alert" tabindex="-1"><strong>Revise a seleção:</strong><ul><?php foreach ($errors as $message): ?><li><?= $e($message) ?></li><?php endforeach ?></ul></section><?php endif ?>

<form class="property-form" method="POST" action="/signboards/<?= $e((string)$signboard['id']) ?>/assign">
    <?= $csrfField ?>
    <fieldset class="signboard-property-picker" aria-describedby="signboard-property-help<?= isset($errors['property_id'])?' signboard-property-error':'' ?>">
        <legend>Imóvel</legend>
        <label for="signboard-property-search">Pesquisar por código, nome ou endereço
            <input id="signboard-property-search" type="search" autocomplete="off" placeholder="Digite para filtrar os imóveis" aria-controls="signboard-property-options" autofocus>
        </label>
        <p id="signboard-property-help">Selecione um imóvel cadastrado para associar à placa.</p>
        <p class="signboard-property-count" id="signboard-property-count" aria-live="polite"><?= count($properties) ?> imóvel(is) disponível(is)</p>
        <div class="signboard-property-options" id="signboard-property-options" role="group" aria-label="Imóveis encontrados">
            <?php foreach($properties as $property): ?>
                <label class="signboard-property-option">
                    <input type="radio" name="property_id" value="<?= $e((string)$property['id']) ?>" required <?= (string)$selectedPropertyId===(string)$property['id']?'checked':'' ?> <?= isset($errors['property_id'])?'aria-invalid="true" aria-describedby="signboard-property-error"':'' ?>>
                    <span><strong><?= $e($property['code']) ?></strong> · <?= $e($property['title']) ?><small><?= $e(implode(', ',array_filter([$property['address'],$property['neighborhood'],$property['city']]))) ?></small></span>
                </label>
            <?php endforeach ?>
            <?php if(!$properties): ?><p class="properties-empty-state">Nenhum imóvel cadastrado para vincular.</p><?php endif ?>
        </div>
        <p id="signboard-property-no-results" role="status" hidden>Nenhum imóvel corresponde à busca.</p>
        <?php if(isset($errors['property_id'])): ?><small class="field-error-text" id="signboard-property-error"><?= $e($errors['property_id']) ?></small><?php endif ?>
    </fieldset>
    <?php if(!$properties): ?><p role="status">Cadastre um imóvel antes de colocar a placa.</p><?php endif ?>
    <div class="property-form-actions"><button type="submit" <?= !$properties?'disabled':'' ?>>Colocar placa</button><a href="/signboards" role="button" class="secondary outline">Cancelar</a></div>
</form>
<script>
(()=>{const search=document.getElementById('signboard-property-search'),list=document.getElementById('signboard-property-options'),count=document.getElementById('signboard-property-count'),noResults=document.getElementById('signboard-property-no-results');if(!search||!list||!count||!noResults)return;const options=[...list.querySelectorAll('.signboard-property-option')];function update(){const query=search.value.trim().toLocaleLowerCase('pt-BR');let visible=0;for(const option of options){const matches=option.textContent.toLocaleLowerCase('pt-BR').includes(query);option.hidden=!matches;if(matches)visible++;}count.textContent=visible+' imóvel(is) disponível(is)';noResults.hidden=visible>0;}search.addEventListener('input',update);update();})();
</script>
