<?php
/**
 * @var ?array<string, mixed> $staging
 * @var int $step
 * @var list<array<string, mixed>> $brokers
 * @var list<array<string, mixed>> $recentBatches
 * @var int $maxFileSizeMb
 * @var int $maxRows
 * @var ?array<string, mixed> $lastResult
 * @var callable $e
 * @var string $csrfToken
 * @var string $csrfField
 */

$lastResult = $lastResult ?? ($_SESSION['last_import_result'] ?? null);
?>

<div style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
                <h1 style="margin-bottom: 0.25rem;">Importação de dados CSV</h1>
            <p style="color: var(--pico-muted-color); margin: 0; font-size: 0.95rem;">
                Imóveis, proprietários e contatos com prévia, conferência por linha e confirmação antes de gravar.
            </p>
        </div>
        <div>
            <a href="/leads" role="button" class="outline secondary" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                &larr; Voltar para Leads
            </a>
        </div>
    </div>
</div>

<!-- Indicador de Progresso (Stepper Semântico WCAG) -->
<nav class="import-stepper" aria-label="Progresso da Importação" style="margin-bottom: 2rem;">
    <ol class="import-stepper-list">
        <li class="import-step-item <?= $step === 1 ? 'is-active' : ($step > 1 ? 'is-completed' : '') ?>" <?= $step === 1 ? 'aria-current="step"' : '' ?>>
            <span class="import-step-badge">1</span>
            <span>Upload do CSV</span>
        </li>
        <li style="color: var(--pico-muted-color); user-select: none;" aria-hidden="true">&rarr;</li>
        <li class="import-step-item <?= $step === 2 ? 'is-active' : ($step > 2 ? 'is-completed' : '') ?>" <?= $step === 2 ? 'aria-current="step"' : '' ?>>
            <span class="import-step-badge">2</span>
            <span>Mapeamento &amp; Prévia</span>
        </li>
        <li style="color: var(--pico-muted-color); user-select: none;" aria-hidden="true">&rarr;</li>
        <li class="import-step-item <?= $step === 3 ? 'is-active' : ($step > 3 ? 'is-completed' : '') ?>" <?= $step === 3 ? 'aria-current="step"' : '' ?>>
            <span class="import-step-badge">3</span>
            <span>Validação &amp; Impacto</span>
        </li>
        <li style="color: var(--pico-muted-color); user-select: none;" aria-hidden="true">&rarr;</li>
        <li class="import-step-item <?= $step === 4 ? 'is-active' : '' ?>" <?= $step === 4 ? 'aria-current="step"' : '' ?>>
            <span class="import-step-badge">4</span>
            <span>Conclusão &amp; Lote</span>
        </li>
    </ol>
</nav>

<?php if ($step === 1): ?>
    <!-- PASSO 1: UPLOAD DO ARQUIVO CSV -->
    <div class="grid" style="grid-template-columns: 1fr; gap: 2rem;">
        <article class="import-panel">
            <header style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0 0 0.5rem 0; font-size: 1.25rem;">Selecione um arquivo de imóveis, proprietários ou contatos</h2>
                    <p style="color: var(--pico-muted-color); margin: 0; font-size: 0.9rem;">
                        O sistema processará os cabeçalhos e exibirá uma prévia de conferência antes de qualquer gravação no banco de dados.
                    </p>
                </div>
                <div>
                    <a href="data:text/csv;charset=utf-8,<?= rawurlencode("Nome;Telefone;E-mail;Origem;Corretor;Observacoes\nBeatriz Mendes;21988887777;beatriz@exemplo.com;site;Carlos Corretor;Interesse em apartamento Icaraí\nRodrigo Silva;21977776666;rodrigo@exemplo.com;whatsapp;;Procura casa linear Piratininga\n") ?>" 
                       download="modelo_importacao_leads.csv" 
                       role="button" 
                       class="outline secondary" 
                       style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.3rem;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Baixar modelo para contatos (.csv)
                    </a>
                </div>
            </header>

            <form action="/leads/import/upload" method="POST">
                <?= $csrfField ?>

                <div class="import-source-picker">
                    <label for="bundled-source">CSV já disponível neste projeto
                        <select id="bundled-source" name="source_file" required>
                            <option value="">-- Escolha uma fonte --</option>
                            <option value="Imóveis-20261020.csv">Imóveis-20261020.csv — Imóveis</option>
                            <option value="Planilha sem título - Proprietários_por_imóveis-20261023 (2).csv">Proprietários_por_imóveis-20261023 (2).csv — Proprietários e vínculos</option>
                            <option value="contacts_filtrados.csv">contacts_filtrados.csv — Contatos / leads</option>
                        </select>
                    </label>
                    <button type="submit" class="outline secondary" style="margin:0">Preparar prévia desta fonte</button>
                </div>
            </form>

            <form action="/leads/import/upload" method="POST" enctype="multipart/form-data">
                <?= $csrfField ?>
                <p style="margin:1rem 0 .75rem;color:var(--pico-muted-color)">Ou envie outro arquivo CSV:</p>

                <div style="border: 2px dashed var(--pico-muted-border-color); border-radius: 8px; padding: 2.5rem 1.5rem; text-align: center; margin-bottom: 1.5rem; background: var(--pico-card-sectioning-background-color);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--pico-primary); margin-bottom: 1rem;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="12" y2="12"></line>
                        <line x1="15" y1="15" x2="12" y2="12"></line>
                    </svg>

                    <div style="margin-bottom: 1rem;">
                        <label for="csv-file-input" style="cursor: pointer; display: block; margin-bottom: 0.5rem;">
                            <span style="font-weight: bold; color: var(--pico-primary);">Selecione o arquivo CSV em seu computador</span>
                        </label>
                        <input type="file" id="csv-file-input" name="file" accept=".csv, text/csv, text/plain" required class="import-file-input">
                    </div>

                    <div style="font-size: 0.8rem; color: var(--pico-muted-color); line-height: 1.5;">
                        <span>Formato aceito: <strong>.csv</strong></span> &bull; 
                        <span>Tamanho máximo: <strong><?= $e((string)$maxFileSizeMb) ?> MB</strong></span> &bull; 
                        <span>Limite por lote: <strong><?= number_format($maxRows, 0, ',', '.') ?> linhas</strong></span> &bull;
                        <span>Codificação: <strong>UTF-8 ou ISO-8859-1</strong></span>
                    </div>
                </div>

                <div class="import-upload-actions">
                    <a href="/leads" role="button" class="outline secondary" style="margin: 0;">Cancelar</a>
                    <button type="submit" class="primary" style="margin: 0;">Enviar e Visualizar Prévia &rarr;</button>
                </div>
            </form>
        </article>

        <!-- Histórico Recente de Lotes Importados -->
        <?php if (!empty($recentBatches)): ?>
            <article class="import-panel import-panel-history">
                <details>
                    <summary style="font-size: 1.05rem; font-weight: bold; cursor: pointer;">
                        Histórico Recente de Lotes Importados (<?= count($recentBatches) ?> lotes)
                    </summary>
                    <div class="table-responsive import-table-scroll" role="region" aria-label="Histórico de lotes importados; tabela com rolagem horizontal" tabindex="0" style="overflow-x: auto; margin-top: 1rem;">
                        <table style="font-size: 0.85rem; margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th>Código do Lote</th>
                                    <th>Arquivo</th>
                                    <th>Linhas</th>
                                    <th>Inserções</th>
                                    <th>Atualizações</th>
                                    <th>Ignorados</th>
                                    <th>Data</th>
                                    <th>Operador</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBatches as $batch): ?>
                                    <tr>
                                        <td><code><?= $e($batch['batch_code']) ?></code></td>
                                        <td><?= $e($batch['filename']) ?></td>
                                        <td><strong><?= (int)$batch['total_rows'] ?></strong></td>
                                        <td style="color: var(--pico-ins-color);">+<?= (int)$batch['created_count'] ?></td>
                                        <td style="color: var(--pico-primary);"><?= (int)$batch['updated_count'] ?></td>
                                        <td style="color: var(--pico-muted-color);"><?= (int)$batch['skipped_count'] ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($batch['created_at'])) ?></td>
                                        <td><?= $e($batch['user_name'] ?? 'Sistema') ?></td>
                                        <td>
                                            <span class="badge" style="font-size: 0.75rem; background: var(--pico-card-background-color); border: 1px solid var(--pico-card-border-color);">
                                                <?= $e($batch['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </article>
        <?php endif; ?>
    </div>

<?php elseif ($step === 2 && $staging !== null): ?>
    <!-- PASSO 2: MAPEAMENTO DE COLUNAS & PRÉVIA -->
    <article class="import-panel">
        <header style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0 0 0.5rem 0; font-size: 1.25rem;">Mapeamento de Colunas &amp; Prévia da Planilha</h2>
                <p style="color: var(--pico-muted-color); margin: 0; font-size: 0.9rem;">
                    Arquivo: <strong><?= $e($staging['filename']) ?></strong> (<?= round($staging['file_size'] / 1024, 1) ?> KB &bull; ~<?= (int)$staging['total_rows_estimate'] ?> linhas estimadas &bull; delimitador: <code><?= $e($staging['delimiter'] === "\t" ? 'TAB' : $staging['delimiter']) ?></code>)
                </p>
            </div>
            <div>
                <form action="/leads/import/cancel" method="POST" style="margin: 0; display: inline;">
                    <?= $csrfField ?>
                    <button type="submit" class="outline secondary" style="font-size: 0.85rem; padding: 0.35rem 0.75rem; margin: 0;" onclick="return confirm('Tem certeza que deseja cancelar? O arquivo temporário será descartado.');">
                        Cancelar e Descartar
                    </button>
                </form>
            </div>
        </header>

        <?php if (!empty($staging['previous_batch'])): ?>
            <div class="import-alert-warning" role="alert" style="margin-bottom: 1.5rem;">
                <h4>Atenção: Planilha já importada anteriormente!</h4>
                <span>Este arquivo possui o mesmo conteúdo (hash SHA-256) do <strong>Lote <?= $e($staging['previous_batch']['batch_code']) ?></strong>, importado em <?= date('d/m/Y H:i', strtotime($staging['previous_batch']['created_at'])) ?> por <?= $e($staging['previous_batch']['user_name'] ?? 'outro operador') ?>. Se prosseguir, os dados serão reprocessados de acordo com a política de duplicidade.</span>
            </div>
        <?php endif; ?>

        <form action="/leads/import/validate" method="POST">
            <?= $csrfField ?>

            <section style="margin-bottom: 2rem;">
                <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--pico-muted-color);">
                    1. Associe as colunas da planilha aos campos do CRM
                </h3>

                <?php $dataset = $staging['dataset'] ?? 'leads'; $customMap = $dataset !== 'leads' || isset($staging['mapping']['first_name']); ?>
                <?php if ($customMap): ?>
                <?php
                    $mapFields = $dataset === 'properties'
                        ? ['code'=>'Ref. do imóvel *','type'=>'Tipo *','sale'=>'Venda','rent'=>'Locação','address'=>'Logradouro *','neighborhood'=>'Bairro','city'=>'Cidade','postal_code'=>'CEP','iptu'=>'IPTU']
                        : ($dataset === 'owners' ? ['name'=>'Nome do proprietário *','phone'=>'Telefones','email'=>'E-mail','property_code'=>'Ref. imóvel *']
                        : ['first_name'=>'First Name *','middle_name'=>'Middle Name','last_name'=>'Last Name','email'=>'E-mail principal','alternate_email'=>'E-mail alternativo','phone'=>'Telefone principal','alternate_phone'=>'Telefone alternativo','notes'=>'Observações (Notes)','property_ref'=>'Código do imóvel (opcional)']);
                ?>
                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <?php foreach ($mapFields as $key => $label): ?>
                    <div>
                        <label for="map-<?= $e($key) ?>" style="font-size: .85rem; font-weight: bold;"><?= $e($label) ?></label>
                        <select id="map-<?= $e($key) ?>" name="mapping[<?= $e($key) ?>]" <?= str_contains($label, '*') ? 'required' : '' ?>>
                            <option value="">-- <?= str_contains($label, '*') ? 'Selecione' : 'Não mapear' ?> --</option>
                            <?php foreach ($staging['headers'] as $idx => $header): ?>
                            <option value="<?= $idx ?>" <?= isset($staging['mapping'][$key]) && (int)$staging['mapping'][$key] === $idx ? 'selected' : '' ?>><?= $idx + 1 ?>. <?= $e($header) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($dataset === 'leads'): ?><p role="status" style="margin-top:.75rem">Os contatos serão importados sem vínculo com imóvel. O código não está presente nos cabeçalhos informados; se existir outra coluna de referência no CSV completo, selecione-a acima.</p><?php endif; ?>
                <?php if ($dataset === 'properties'): ?>
                    <label for="zero-policy" style="max-width: 520px; margin-top: 1rem;">Como interpretar valores <code>0,00</code> nesta planilha?
                        <select id="zero-policy" name="zero_policy">
                            <option value="absent" <?= ($staging['zero_policy'] ?? 'absent') === 'absent' ? 'selected' : '' ?>>Ausência / sem cobrança (recomendado)</option>
                            <option value="zero" <?= ($staging['zero_policy'] ?? '') === 'zero' ? 'selected' : '' ?>>Valor zero informado</option>
                        </select>
                    </label>
                <?php endif; ?>
                <?php else: ?>
                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <!-- Campo Nome (Obrigatório) -->
                    <div>
                        <label for="map-name" style="font-size: 0.85rem; font-weight: bold;">
                            Nome do Lead <span style="color: var(--pico-del-color);">*</span>
                        </label>
                        <select id="map-name" name="mapping[name]" required>
                            <option value="">-- Selecione a coluna --</option>
                            <?php foreach ($staging['headers'] as $idx => $header): ?>
                                <option value="<?= $idx ?>" <?= isset($staging['mapping']['name']) && (int)$staging['mapping']['name'] === $idx ? 'selected' : '' ?>>
                                    <?= $idx + 1 ?>. <?= $e($header) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo Telefone -->
                    <div>
                        <label for="map-phone" style="font-size: 0.85rem; font-weight: bold;">
                            Telefone / WhatsApp
                        </label>
                        <select id="map-phone" name="mapping[phone]">
                            <option value="">-- Não mapear --</option>
                            <?php foreach ($staging['headers'] as $idx => $header): ?>
                                <option value="<?= $idx ?>" <?= isset($staging['mapping']['phone']) && (int)$staging['mapping']['phone'] === $idx ? 'selected' : '' ?>>
                                    <?= $idx + 1 ?>. <?= $e($header) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo E-mail -->
                    <div>
                        <label for="map-email" style="font-size: 0.85rem; font-weight: bold;">
                            E-mail
                        </label>
                        <select id="map-email" name="mapping[email]">
                            <option value="">-- Não mapear --</option>
                            <?php foreach ($staging['headers'] as $idx => $header): ?>
                                <option value="<?= $idx ?>" <?= isset($staging['mapping']['email']) && (int)$staging['mapping']['email'] === $idx ? 'selected' : '' ?>>
                                    <?= $idx + 1 ?>. <?= $e($header) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo Origem / Canal -->
                    <div>
                        <label for="map-source" style="font-size: 0.85rem; font-weight: bold;">
                            Origem do Contato
                        </label>
                        <select id="map-source" name="mapping[source]">
                            <option value="">-- Padrão (importacao_csv) --</option>
                            <?php foreach ($staging['headers'] as $idx => $header): ?>
                                <option value="<?= $idx ?>" <?= isset($staging['mapping']['source']) && (int)$staging['mapping']['source'] === $idx ? 'selected' : '' ?>>
                                    <?= $idx + 1 ?>. <?= $e($header) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo Observações -->
                    <div>
                        <label for="map-notes" style="font-size: 0.85rem; font-weight: bold;">
                            Observações / Notas
                        </label>
                        <select id="map-notes" name="mapping[notes]">
                            <option value="">-- Não mapear --</option>
                            <?php foreach ($staging['headers'] as $idx => $header): ?>
                                <option value="<?= $idx ?>" <?= isset($staging['mapping']['notes']) && (int)$staging['mapping']['notes'] === $idx ? 'selected' : '' ?>>
                                    <?= $idx + 1 ?>. <?= $e($header) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo Corretor da Linha (Opcional) -->
                    <div>
                        <label for="map-broker" style="font-size: 0.85rem; font-weight: bold;">
                            Coluna com Corretor Responsável
                        </label>
                        <select id="map-broker" name="mapping[broker]">
                            <option value="">-- Usar corretor padrão abaixo --</option>
                            <?php foreach ($staging['headers'] as $idx => $header): ?>
                                <option value="<?= $idx ?>" <?= isset($staging['mapping']['broker']) && (int)$staging['mapping']['broker'] === $idx ? 'selected' : '' ?>>
                                    <?= $idx + 1 ?>. <?= $e($header) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Atribuição Padrão de Corretor -->
                    <div>
                        <label for="default-broker" style="font-size: 0.85rem; font-weight: bold;">
                            Corretor Responsável Padrão
                        </label>
                        <select id="default-broker" name="default_broker_id">
                            <option value="">Atribuir ao operador atual</option>
                            <?php foreach ($brokers as $broker): ?>
                                <option value="<?= (int)$broker['id'] ?>">
                                    <?= $e($broker['name']) ?> (<?= $e($broker['role_label'] ?? $broker['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <?php
                $mappedIndexes = array_map('intval', array_filter($staging['mapping'] ?? [], static fn($v) => $v !== '' && $v !== null));
                $unmappedHeaders = array_values(array_filter($staging['headers'], static fn($hidx) => !in_array($hidx, $mappedIndexes, true), ARRAY_FILTER_USE_KEY));
            ?>
            <?php if ($unmappedHeaders): ?>
                <p class="import-alert-warning" role="alert"><strong>Colunas sem destino:</strong> <?= $e(implode(', ', $unmappedHeaders)) ?>. Elas não serão descartadas: cada linha original será preservada no registro de origem do lote.</p>
            <?php endif; ?>

            <!-- Amostra Bruta das Primeiras Linhas -->
            <section style="margin-bottom: 2rem;">
                <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--pico-muted-color);">
                    2. Amostra dos dados brutos lidos (primeiras 5 linhas)
                </h3>
                <div class="table-responsive import-table-scroll" role="region" aria-label="Amostra do CSV; tabela com rolagem horizontal" tabindex="0" style="overflow-x: auto; background: var(--pico-card-sectioning-background-color); border: 1px solid var(--pico-card-border-color); border-radius: 6px; padding: 0.5rem;">
                    <table style="font-size: 0.8rem; margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <?php foreach ($staging['headers'] as $h): ?>
                                    <th><code><?= $e($h) ?></code></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staging['preview_rows'] as $rIdx => $row): ?>
                                <tr>
                                    <td><strong><?= $rIdx + 1 ?></strong></td>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?= $e($cell ?: '—') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <span style="font-size: 0.85rem; color: var(--pico-muted-color);">
                    O mapeamento pode ser alterado e revalidado antes da gravação definitiva.
                </span>
                <button type="submit" class="primary" style="margin: 0;">
                    Avançar para Validação e Impacto &rarr;
                </button>
            </div>
        </form>
    </article>

<?php elseif ($step === 3 && $staging !== null && !empty($staging['validation'])): ?>
    <!-- PASSO 3: VALIDAÇÃO & IMPACTO PREVISTO (DRY-RUN) -->
    <?php $val = $staging['validation']; ?>
    <article class="import-panel">
        <header style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0 0 0.5rem 0; font-size: 1.25rem;">Impacto Previsto da Importação</h2>
                <p style="color: var(--pico-muted-color); margin: 0; font-size: 0.9rem;">
                    Nenhum dado foi gravado no banco ainda. Confira o impacto antes de confirmar.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <form action="/leads/import/back" method="POST" style="margin: 0; display: inline;">
                    <?= $csrfField ?>
                    <button type="submit" class="outline secondary" style="font-size: 0.85rem; padding: 0.35rem 0.75rem; margin: 0;">
                        &larr; Corrigir Mapeamento
                    </button>
                </form>
            </div>
        </header>

        <!-- Cartões de Impacto Previsto com Classes Semânticas -->
        <div class="import-stat-grid">
            <div class="import-stat-card">
                <span class="stat-label">Total Analisado</span>
                <div class="stat-value"><?= (int)$val['total_rows'] ?></div>
                <span class="stat-desc">linhas analisadas</span>
            </div>

            <div class="import-stat-card is-new">
                <span class="stat-label"><?= ($staging['dataset'] ?? 'leads') === 'properties' ? 'Inserções' : (($staging['dataset'] ?? '') === 'owners' ? 'Novos proprietários' : 'Novos contatos') ?></span>
                <div class="stat-value">+<?= (int)$val['new_count'] ?></div>
                <span class="stat-desc">cadastros inéditos</span>
            </div>

            <div class="import-stat-card is-duplicate">
                <span class="stat-label">Duplicidades detectadas</span>
                <div class="stat-value"><?= (int)$val['duplicate_count'] ?></div>
            </div>

            <div class="import-stat-card">
                <span class="stat-label">Atualizações previstas</span>
                <div class="stat-value"><?= ($staging['duplicate_policy'] ?? 'merge') === 'merge' ? (int)$val['duplicate_count'] : 0 ?></div>
            </div>

            <div class="import-stat-card">
                <span class="stat-label">Ignorados</span>
                <div class="stat-value"><?= ($staging['duplicate_policy'] ?? 'merge') === 'skip' ? (int)$val['duplicate_count'] : 0 ?></div>
            </div>

            <div class="import-stat-card is-error">
                <span class="stat-label">Linhas com Erros</span>
                <div class="stat-value"><?= (int)$val['error_count'] ?></div>
                <span class="stat-desc">não serão importadas</span>
            </div>
        </div>

        <?php if (!empty($val['warnings_list'])): ?>
            <details style="margin-bottom:1.5rem"><summary>Alertas de complementação (<?= (int)($val['warnings_count'] ?? count($val['warnings_list'])) ?> no total; amostra de até 100)</summary><ul><?php foreach ($val['warnings_list'] as $warning): ?><li>Linha <?= (int)$warning['row'] ?>: <?= $e($warning['reason']) ?></li><?php endforeach; ?></ul></details>
        <?php endif; ?>

        <!-- FORMULÁRIO DE CONFIRMAÇÃO (DESACOPLADO DE CANCELAMENTO) -->
        <form id="form-confirm-import" action="/leads/import/confirm" method="POST">
            <?= $csrfField ?>

            <!-- Política de Duplicidade Visível Antes da Confirmação -->
            <section style="background: var(--pico-card-sectioning-background-color); border: 1px solid var(--pico-card-border-color); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="font-size: 1rem; margin: 0 0 1rem 0;">Impacto nos repetidos (<?= (int)$val['duplicate_count'] ?> detectados)</h3>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; margin: 0;">
                        <input type="radio" name="duplicate_policy" value="merge" <?= ($staging['duplicate_policy'] ?? 'merge') === 'merge' ? 'checked' : '' ?> style="margin-top: 0.25rem;">
                        <div>
                        <strong>Mesclar campos vazios e registrar a origem</strong>
                            <div style="font-size: 0.85rem; color: var(--pico-muted-color);">
                                Atualiza somente lacunas em contatos; em imóveis aplica os campos mapeados; proprietários são vinculados sem duplicação. Campos adicionais ficam preservados como dados de origem.
                            </div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; margin: 0;">
                        <input type="radio" name="duplicate_policy" value="skip" <?= ($staging['duplicate_policy'] ?? 'merge') === 'skip' ? 'checked' : '' ?> style="margin-top: 0.25rem;">
                        <div>
                            <strong>Ignorar registros repetidos</strong>
                            <div style="font-size: 0.85rem; color: var(--pico-muted-color);">
                                Não altera registros existentes nem cria novos vínculos para as linhas repetidas.
                            </div>
                        </div>
                    </label>
                </div>
            </section>

            <!-- Alerta e Confirmação de Reexecução de Mesmo Lote -->
            <?php if (!empty($staging['previous_batch'])): ?>
                <section class="import-alert-warning" style="margin-bottom: 2rem;">
                    <h4>Atenção: Confirmação de Reexecução de Lote</h4>
                    <p style="font-size: 0.85rem; margin-bottom: 0.75rem;">
                        Este mesmo arquivo já foi importado no lote <strong><?= $e($staging['previous_batch']['batch_code']) ?></strong> em <?= date('d/m/Y H:i', strtotime($staging['previous_batch']['created_at'])) ?> por <?= $e($staging['previous_batch']['user_name'] ?? 'Administrador') ?>.
                    </p>
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: bold; cursor: pointer; margin: 0;">
                        <input type="checkbox" name="allow_duplicate_batch" value="1" required>
                        Estou ciente e confirmo que desejo reexecutar a importação deste lote.
                    </label>
                </section>
            <?php endif; ?>

            <!-- Tabela de Linhas com Erros Identificados (se houver) -->
            <?php if (!empty($val['errors_list'])): ?>
                <details style="margin-bottom: 2rem; background: var(--pico-card-sectioning-background-color); border: 1px solid var(--pico-card-border-color); border-radius: 8px; padding: 1rem;">
                    <summary style="font-weight: bold; color: var(--pico-del-color); cursor: pointer;">
                        Ver detalhes das <?= count($val['errors_list']) ?> linhas com erros identificados
                    </summary>
                    <div class="table-responsive import-table-scroll" role="region" aria-label="Linhas inválidas; tabela com rolagem horizontal" tabindex="0" style="margin-top: 1rem; overflow-x: auto;">
                        <table style="font-size: 0.8rem; margin: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Linha #</th>
                                    <th>Nome Identificado</th>
                                    <th>Motivo da Recusa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($val['errors_list'] as $err): ?>
                                    <tr>
                                        <td><code><?= (int)$err['row'] ?></code></td>
                                    <td><?= $e($err['name'] ?? $err['code'] ?? '—') ?></td>
                                        <td style="color: var(--pico-del-color);"><?= $e($err['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>

            <!-- Amostra de Duplicidades Identificadas (se houver) -->
            <?php if (!empty($val['duplicates_sample']) || !empty($val['duplicates_list'])): ?>
                <details style="margin-bottom: 2rem; background: var(--pico-card-sectioning-background-color); border: 1px solid var(--pico-card-border-color); border-radius: 8px; padding: 1rem;">
                    <summary style="font-weight: bold; color: var(--pico-primary); cursor: pointer;">
                        Ver duplicidades identificadas (<?= count($val['duplicates_sample'] ?? $val['duplicates_list']) ?> exibidas)
                    </summary>
                    <div class="table-responsive import-table-scroll" role="region" aria-label="Duplicidades encontradas; tabela com rolagem horizontal" tabindex="0" style="margin-top: 1rem; overflow-x: auto;">
                        <table style="font-size: 0.8rem; margin: 0;">
                            <thead>
                                <tr>
                                    <th>Linha #</th>
                                    <th>Registro</th>
                                    <th>Referência / contato</th>
                                    <th>Diagnóstico de Duplicidade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($val['duplicates_sample'] ?? $val['duplicates_list']) as $dup): ?>
                                    <tr>
                                        <td><code><?= (int)$dup['row'] ?></code></td>
                                        <td><?= $e($dup['name'] ?? $dup['code'] ?? 'Linha '.$dup['row']) ?></td>
                                        <td>
                                            <?= $e($dup['phone'] ?? '') ?> <?= $e($dup['email'] ?? '') ?> <?= $e($dup['reason'] ?? '') ?>
                                        </td>
                                        <td><span class="badge" style="font-size: 0.75rem;"><?= $e($dup['duplicate_reason']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </form>

        <!-- AÇÕES DE RODAPÉ (Sem tags <form> aninhadas!) -->
        <div class="import-review-actions">
            <form action="/leads/import/cancel" method="POST" style="margin: 0; display: inline;">
                <?= $csrfField ?>
                <button type="submit" class="outline secondary" style="margin: 0;" onclick="return confirm('Tem certeza que deseja cancelar? O arquivo temporário será descartado.');">
                    Cancelar e Descartar
                </button>
            </form>

            <button type="submit" form="form-confirm-import" class="primary" style="margin: 0; font-weight: bold;">
                Confirmar importação (<?= (int)$val['new_count'] ?> inserções, <?= ($staging['duplicate_policy'] ?? 'merge') === 'skip' ? (int)$val['duplicate_count'] . ' ignorados' : (int)$val['duplicate_count'] . ' atualizações' ?>, <?= (int)$val['error_count'] ?> inválidos) &rarr;
            </button>
        </div>
    </article>

<?php elseif ($step === 4 && $lastResult !== null): ?>
    <!-- PASSO 4: CONCLUSÃO & SUMÁRIO DO LOTE -->
    <article class="import-panel import-panel-success">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: rgba(34, 197, 94, 0.15); color: var(--pico-ins-color); margin-bottom: 1.5rem;">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>

        <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;">Importação Concluída com Sucesso!</h2>
        <p style="color: var(--pico-muted-color); font-size: 0.95rem; margin-bottom: 1.5rem;">
                    O lote foi gravado em transação, com contagens finais e linhas de origem preservadas para conferência.
        </p>

        <div style="display: inline-block; background: var(--pico-card-sectioning-background-color); border: 1px solid var(--pico-card-border-color); border-radius: 8px; padding: 0.75rem 1.5rem; margin-bottom: 2rem;">
            <span style="font-size: 0.8rem; text-transform: uppercase; color: var(--pico-muted-color); font-weight: bold;">Identificador do Lote</span>
            <div style="font-size: 1.35rem; font-weight: bold; font-family: monospace; color: var(--pico-primary); margin-top: 0.25rem;">
                <?= $e($lastResult['batch_code']) ?>
            </div>
        </div>

        <!-- Métricas Consolidadas -->
        <div class="import-stat-grid" style="max-width: 650px; margin: 0 auto 2rem auto;">
            <div class="import-stat-card">
                <span class="stat-label">Linhas</span>
                <div class="stat-value"><?= (int)$lastResult['total_rows'] ?></div>
            </div>
            <div class="import-stat-card is-new">
                <span class="stat-label">Inserções</span>
                <div class="stat-value">+<?= (int)$lastResult['created_count'] ?></div>
            </div>
            <div class="import-stat-card is-duplicate">
                <span class="stat-label">Atualizações</span>
                <div class="stat-value"><?= (int)$lastResult['updated_count'] ?></div>
            </div>
            <div class="import-stat-card">
                <span class="stat-label">Ignorados</span>
                <div class="stat-value"><?= (int)$lastResult['skipped_count'] ?></div>
            </div>
            <div class="import-stat-card is-error">
                <span class="stat-label">Inválidos</span>
                <div class="stat-value"><?= (int)$lastResult['error_count'] ?></div>
            </div>
            <?php if (!empty($lastResult['linked_count'])): ?><div class="import-stat-card"><span class="stat-label">Vínculos criados</span><div class="stat-value"><?= (int)$lastResult['linked_count'] ?></div></div><?php endif; ?>
        </div>

        <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
            <a href="/leads/import" role="button" class="outline" style="margin: 0;">
                Importar Outra Planilha
            </a>
            <a href="/leads" role="button" class="primary" style="margin: 0;">
                Visualizar Carteira de Leads &rarr;
            </a>
        </div>
    </article>
<?php endif; ?>
