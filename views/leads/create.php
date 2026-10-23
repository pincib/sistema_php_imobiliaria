<?php
/**
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var list<array<string, mixed>> $brokers
 * @var array<string, string> $sources
 * @var array<string, mixed>|null $currentUser
 * @var callable $e
 * @var string $csrfField
 */
?>

<div style="max-width: 680px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="/leads" style="font-size: 0.85rem; color: var(--pico-muted-color); text-decoration: none;">
            &larr; Voltar para lista de leads
        </a>
        <h1 style="margin-top: 0.5rem; margin-bottom: 0.25rem;">Novo Lead</h1>
        <p style="color: var(--pico-muted-color); margin: 0;">
            Cadastre uma oportunidade de negócio para acompanhamento no pipeline comercial.
        </p>
    </div>

    <article style="border: 1px solid var(--pico-muted-border-color); padding: 1.75rem;">
        <form method="POST" action="/leads" novalidate>
            <?= $csrfField ?>

            <!-- Nome do Lead -->
            <label for="name">
                Nome do Lead / Cliente *
                <input type="text" 
                       id="name" 
                       name="name" 
                       value="<?= $e($values['name'] ?? '') ?>" 
                       placeholder="Ex: João da Silva" 
                       required 
                       autofocus
                       class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                       aria-invalid="<?= !empty($errors['name']) ? 'true' : 'false' ?>" <?= !empty($errors['name']) ? 'aria-describedby="name-error"' : '' ?>>
            </label>
            <?php if (!empty($errors['name'])): ?>
                <span id="name-error" class="field-error-text" role="alert"><?= $e($errors['name']) ?></span>
            <?php endif; ?>

            <label for="property_code">Código do imóvel de interesse
                <input id="property_code" name="property_code" list="lead-property-codes" maxlength="40" value="<?= $e($values['property_code'] ?? '') ?>" placeholder="Opcional — digite ou selecione o código" aria-invalid="<?= !empty($errors['property_code']) ? 'true' : 'false' ?>" <?= !empty($errors['property_code']) ? 'aria-describedby="property-code-error"' : '' ?>>
                <datalist id="lead-property-codes"><?php foreach (($properties ?? []) as $property): ?><option value="<?= $e($property['code']) ?>"><?= $e($property['title']) ?></option><?php endforeach; ?></datalist>
            </label>
            <?php if (!empty($errors['property_code'])): ?><span id="property-code-error" class="field-error-text" role="alert"><?= $e($errors['property_code']) ?></span><?php endif; ?>

            <!-- Contatos: Telefone e E-mail -->
            <div class="grid">
                <div>
                    <label for="phone">
                        Telefone / WhatsApp
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               value="<?= $e($values['phone'] ?? '') ?>" 
                               placeholder="(21) 99999-8888" 
                               class="<?= !empty($errors['phone']) ? 'is-invalid' : '' ?>"
                               aria-invalid="<?= !empty($errors['phone']) ? 'true' : 'false' ?>" <?= !empty($errors['phone']) ? 'aria-describedby="phone-error"' : '' ?>>
                    </label>
                    <?php if (!empty($errors['phone'])): ?>
                        <span id="phone-error" class="field-error-text" role="alert"><?= $e($errors['phone']) ?></span>
                    <?php else: ?>
                        <small style="display: block; margin-top: -0.5rem; margin-bottom: 0.75rem; color: var(--pico-muted-color); font-size: 0.75rem;">
                            DDD + número (ex: 21988887777)
                        </small>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="email">
                        E-mail
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?= $e($values['email'] ?? '') ?>" 
                               placeholder="cliente@exemplo.com" 
                               class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                               aria-invalid="<?= !empty($errors['email']) ? 'true' : 'false' ?>" <?= !empty($errors['email']) ? 'aria-describedby="email-error"' : '' ?>>
                    </label>
                    <?php if (!empty($errors['email'])): ?>
                        <span id="email-error" class="field-error-text" role="alert"><?= $e($errors['email']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Origem e Responsável -->
            <div class="grid">
                <div>
                    <label for="source">
                        Canal de Origem
                        <select id="source" name="source" class="<?= !empty($errors['source']) ? 'is-invalid' : '' ?>" aria-invalid="<?= !empty($errors['source']) ? 'true' : 'false' ?>" <?= !empty($errors['source']) ? 'aria-describedby="source-error"' : '' ?>>
                            <?php foreach ($sources as $sourceKey => $sourceLabel): ?>
                                <option value="<?= $e($sourceKey) ?>" <?= ($values['source'] ?? 'manual') === $sourceKey ? 'selected' : '' ?>>
                                    <?= $e($sourceLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if (!empty($errors['source'])): ?>
                        <span id="source-error" class="field-error-text" role="alert"><?= $e($errors['source']) ?></span>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="user_id">
                        Corretor Responsável
                        <select id="user_id" name="user_id">
                            <?php foreach ($brokers as $broker): ?>
                                <?php $selected = (string)($values['user_id'] ?? ($currentUser['id'] ?? '')) === (string)$broker['id']; ?>
                                <option value="<?= $e((string)$broker['id']) ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= $e($broker['name']) ?> (<?= $e($broker['role_label']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>

            <!-- Observações e Preferências do Lead -->
                <label for="notes">
                Observações Iniciais / Perfil de Interesse
                <textarea id="notes" 
                          name="notes" 
                          rows="3" maxlength="4000"
                          placeholder="Ex: Busca apartamento de 2 quartos em Icaraí, orçamento até 500 mil..." aria-invalid="<?= !empty($errors['notes']) ? 'true' : 'false' ?>" <?= !empty($errors['notes']) ? 'aria-describedby="notes-error"' : '' ?>><?= $e($values['notes'] ?? '') ?></textarea>
            </label>
            <?php if (!empty($errors['notes'])): ?><span id="notes-error" class="field-error-text" role="alert"><?= $e($errors['notes']) ?></span><?php endif; ?>

            <!-- Agendamento Opcional do Primeiro Follow-up -->
            <label for="next_follow_up_at" style="margin-top: 1rem;">
                Agendar Primeiro Retorno (Follow-up)
                <input type="datetime-local" 
                       id="next_follow_up_at" 
                       name="next_follow_up_at" 
                       value="<?= $e($values['next_follow_up_at'] ?? '') ?>">
                <small style="display: block; color: var(--pico-muted-color); font-size: 0.75rem;">
                    Opcional: Defina a data/hora para o primeiro contato para aparecer na fila da Dashboard.
                </small>
            </label>

            <!-- Ações -->
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <a href="/leads" role="button" class="outline secondary">Cancelar</a>
                <button type="submit" class="primary">Salvar e Iniciar Atendimento</button>
            </div>
        </form>
    </article>
</div>
