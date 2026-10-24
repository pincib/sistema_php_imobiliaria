<?php

declare(strict_types=1);

namespace App\Modules\Leads\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Modules\Leads\Services\LeadImportService;
use App\Modules\Leads\Services\MultiDatasetImportService;
use App\Modules\Leads\Services\LeadService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class LeadImportController
{
    private LeadImportService $importService;
    private LeadService $leadService;
    private Renderer $renderer;
    private MultiDatasetImportService $multiImport;

    public function __construct(
        ?LeadImportService $importService = null,
        ?LeadService $leadService = null,
        ?Renderer $renderer = null
    ) {
        $this->importService = $importService ?? new LeadImportService();
        $this->leadService = $leadService ?? new LeadService();
        $this->renderer = $renderer ?? new Renderer();
        $this->multiImport = new MultiDatasetImportService();
    }

    /**
     * Tela principal do assistente de importação.
     * Exibe o passo atual com base no estado do staging da sessão.
     */
    public function index(Request $request, Response $response): Response
    {
        $staging = $_SESSION['import_staging'] ?? null;
        $batchRepo = $this->importService->getBatchRepository();
        $brokers = $this->leadService->getRepository()->getActiveBrokers();
        $recentBatches = $batchRepo->listRecent(10);
        $queryParams = $request->getQueryParams();
        $requestedDataset = (string)($queryParams['dataset'] ?? 'auto');
        if (!in_array($requestedDataset, ['auto', 'leads', 'properties', 'owners'], true)) {
            $requestedDataset = 'auto';
        }
        $requestedReturnTo = $this->normalizeReturnContext($queryParams['return_to'] ?? null, $requestedDataset);

        // Se houver staging ativo na sessão, verifica se o arquivo ainda existe no disco
        if ($staging !== null) {
            $path = $this->importService->getStagingPath($staging['id'] ?? '');
            if (!file_exists($path)) {
                unset($_SESSION['import_staging']);
                $staging = null;
            }
        }

        $step = 1;
        if (isset($queryParams['result']) && !empty($_SESSION['last_import_result'])) {
            $step = 4;
        } elseif ($staging !== null) {
            $step = $staging['step'] ?? 2;
        }
        $selectedDataset = $staging['dataset'] ?? ($step === 4
            ? ($_SESSION['last_import_result']['kind'] ?? 'auto')
            : $requestedDataset);
        $returnTo = $staging['return_to'] ?? ($step === 4
            ? ($_SESSION['last_import_result']['return_to'] ?? $requestedReturnTo)
            : $requestedReturnTo);
        $returnPath = $staging['return_path'] ?? ($step === 4
            ? ($_SESSION['last_import_result']['return_path'] ?? $this->returnPathForContext($returnTo))
            : $this->returnPathForContext($returnTo));

        $html = $this->renderer->render('leads/import', [
            'title' => 'Assistente de Importação de Planilhas — CRM Imobiliário',
            'staging' => $staging,
            'step' => $step,
            'brokers' => $brokers,
            'recentBatches' => $recentBatches,
            'maxFileSizeMb' => (int)(LeadImportService::MAX_FILE_SIZE / 1048576),
            'maxRows' => LeadImportService::MAX_ROWS,
            'lastResult' => $_SESSION['last_import_result'] ?? null,
            'selectedDataset' => $selectedDataset,
            'returnTo' => $returnTo,
            'returnPath' => $returnPath,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Passo 1: Recebe o upload do arquivo CSV e gera prévia/mapeamento sugerido.
     */
    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $csvFile = $uploadedFiles['file'] ?? null;
        $params = (array)$request->getParsedBody();
        $requestedDataset = (string)($params['dataset'] ?? 'auto');

        if (!in_array($requestedDataset, ['auto', 'leads', 'properties', 'owners'], true)) {
            SessionManager::flash('error', 'Tipo de importação inválido. Selecione contatos, imóveis ou proprietários.');
            return $response->withHeader('Location', '/leads/import')->withStatus(302);
        }
        $returnTo = $this->normalizeReturnContext($params['return_to'] ?? null, $requestedDataset);

        if (!$csvFile && empty($params['source_file'])) {
            SessionManager::flash('error', 'Nenhum arquivo foi selecionado.');
            return $response->withHeader('Location', $this->importUrlForDataset($requestedDataset, $returnTo))->withStatus(302);
        }

        try {
            $staged = !empty($params['source_file'])
                ? $this->importService->stageBundledFile((string)$params['source_file'])
                : $this->importService->stageUpload($csvFile);
            $detectedDataset = $this->multiImport->detect($staged['headers']);
            if ($requestedDataset === 'auto' && $detectedDataset === null) {
                $this->importService->cancelStaging($staged['staging_id']);
                throw new \InvalidArgumentException('Não foi possível identificar o tipo pelos cabeçalhos. Envie o CSV novamente e escolha contatos, imóveis ou proprietários.');
            }
            $dataset = $requestedDataset === 'auto' ? ($detectedDataset ?? 'leads') : $requestedDataset;
            $mapping = $this->multiImport->suggestedMapping($staged['headers'], $dataset);

            $_SESSION['import_staging'] = [
                'id' => $staged['staging_id'],
                'filename' => $staged['filename'],
                'file_size' => $staged['file_size'],
                'file_hash' => $staged['file_hash'],
                'delimiter' => $staged['delimiter'],
                'headers' => $staged['headers'],
                'preview_rows' => $staged['preview_rows'],
                'total_rows_estimate' => $staged['total_rows_estimate'],
                'mapping' => $dataset === 'leads' ? array_merge($staged['suggested_mapping'], $mapping) : $mapping,
                'dataset' => $dataset,
                'return_to' => $returnTo,
                'return_path' => $this->returnPathForContext($returnTo),
                'detected_dataset' => $detectedDataset,
                'dataset_was_selected' => $requestedDataset !== 'auto',
                'previous_batch' => $staged['previous_batch'],
                'duplicate_policy' => LeadImportService::POLICY_MERGE,
                'default_broker_id' => null,
                'validation' => null,
                'step' => 2, // Avança para o Passo 2: Mapeamento
            ];

            SessionManager::flash('success', 'Planilha enviada com sucesso! Verifique a prévia e confirme o mapeamento das colunas.');
        } catch (Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }

        return $response->withHeader('Location', $this->importUrlForDataset($requestedDataset, $returnTo))->withStatus(302);
    }

    /**
     * Passo 2: Atualiza o mapeamento e executa a validação (dry-run) de impacto.
     */
    public function validate(Request $request, Response $response): Response
    {
        $staging = $_SESSION['import_staging'] ?? null;
        if ($staging === null) {
            SessionManager::flash('error', 'Sessão de importação expirou. Por favor, reenvie a planilha.');
            return $response->withHeader('Location', '/leads/import')->withStatus(302);
        }

        $params = (array)$request->getParsedBody();
        // Use o mapeamento sugerido como fallback para formulários enviados sem algum campo.
        // Valores vazios enviados explicitamente continuam substituindo a sugestão.
        $mapping = array_replace((array)($staging['mapping'] ?? []), (array)($params['mapping'] ?? []));
        $duplicatePolicy = ($params['duplicate_policy'] ?? '') === LeadImportService::POLICY_SKIP
            ? LeadImportService::POLICY_SKIP
            : LeadImportService::POLICY_MERGE;
        $defaultBrokerId = !empty($params['default_broker_id']) ? (int)$params['default_broker_id'] : null;
        $zeroPolicy = ($params['zero_policy'] ?? 'absent') === 'zero' ? 'zero' : 'absent';

        try {
            $kind = $staging['dataset'] ?? 'leads';
            $requiredFields = match ($kind) {
                'properties' => ['code', 'type', 'address'],
                'owners' => ['name', 'property_code'],
                default => ['name'],
            };
            $validIndexes = [];
            foreach ($mapping as $field => $index) {
                if ($index === '' || $index === null) continue;
                if (filter_var($index, FILTER_VALIDATE_INT) === false || (int)$index < 0 || (int)$index >= count($staging['headers'])) {
                    throw new \InvalidArgumentException('Uma coluna mapeada não existe no arquivo. Confira o mapeamento e tente novamente.');
                }
                $validIndexes[$field] = (int)$index;
            }
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $validIndexes)) {
                    $requiredNames = ['code' => 'referência do imóvel', 'type' => 'tipo do imóvel', 'address' => 'logradouro', 'name' => 'nome', 'first_name' => 'nome'];
                    $label = $requiredNames[$field] ?? $field;
                    throw new \InvalidArgumentException('Mapeie a coluna obrigatória de ' . $label . ' antes de validar.');
                }
            }
            $mapping = $validIndexes;
            if ($kind === 'leads' && !$this->usesMultiDatasetImport($kind, $staging, $mapping)) {
                $validation = $this->importService->validateBatch($staging['id'], $mapping, $duplicatePolicy, $defaultBrokerId);
            } else {
                $validation = $this->multiImport->analyze($this->importService->getStagingPath($staging['id']), $kind, $mapping, $staging['delimiter'], $staging['filename'], $zeroPolicy);
            }

            // Atualiza sessão
            $_SESSION['import_staging']['mapping'] = $mapping;
            $_SESSION['import_staging']['zero_policy'] = $zeroPolicy;
            $_SESSION['import_staging']['duplicate_policy'] = $duplicatePolicy;
            $_SESSION['import_staging']['default_broker_id'] = $defaultBrokerId;
            $_SESSION['import_staging']['validation'] = $validation;
            $_SESSION['import_staging']['step'] = 3; // Passo 3: Validação & Prévia de Impacto

            SessionManager::flash('info', 'Validação concluída! Verifique o impacto previsto antes de confirmar a gravação.');
        } catch (Throwable $e) {
            SessionManager::flash('error', 'Falha ao validar planilha: ' . $e->getMessage());
        }

        return $response->withHeader('Location', '/leads/import')->withStatus(302);
    }

    /**
     * Permite ao operador voltar ao Passo 2 (Mapeamento) para corrigir associações de colunas.
     */
    public function backToMapping(Request $request, Response $response): Response
    {
        if (isset($_SESSION['import_staging'])) {
            $_SESSION['import_staging']['step'] = 2;
        }
        return $response->withHeader('Location', '/leads/import')->withStatus(302);
    }

    /**
     * Passo 3 ➔ 4: Confirmação e Execução atômica da importação em lote.
     */
    public function confirm(Request $request, Response $response): Response
    {
        $staging = $_SESSION['import_staging'] ?? null;
        if ($staging === null) {
            SessionManager::flash('error', 'Sessão de importação expirou. Por favor, inicie um novo envio.');
            return $response->withHeader('Location', '/leads/import')->withStatus(302);
        }

        $params = (array)$request->getParsedBody();
        $allowDuplicateBatch = !empty($params['allow_duplicate_batch']);

        $currentUser = AuthService::user();
        $actorUserId = $currentUser ? (int)$currentUser['id'] : 1;

        $mapping = $staging['mapping'] ?? [];
        $duplicatePolicy = $staging['duplicate_policy'] ?? LeadImportService::POLICY_MERGE;
        $defaultBrokerId = $staging['default_broker_id'] ?? null;

        try {
            $kind = $staging['dataset'] ?? 'leads';
            if ($this->usesMultiDatasetImport($kind, $staging, $mapping)) {
                $validation = $staging['validation'] ?? null;
                if (!$validation || ($validation['kind'] ?? null) !== $kind) throw new \InvalidArgumentException('Valide novamente o mapeamento antes de confirmar.');
                if (!empty($staging['previous_batch']) && !$allowDuplicateBatch) throw new \InvalidArgumentException('Este arquivo já foi importado. Marque a confirmação de reexecução antes de prosseguir.');
                $result = $this->multiImport->execute($validation, $staging['filename'], $staging['file_hash'], $actorUserId, $duplicatePolicy);
                $this->importService->cancelStaging($staging['id']);
            } else $result = $this->importService->executeImport(
                $staging['id'],
                $mapping,
                $duplicatePolicy,
                $actorUserId,
                $defaultBrokerId,
                $allowDuplicateBatch
            );

            // Limpa o staging da sessão pois a importação foi concluída com sucesso
            unset($_SESSION['import_staging']);

            // Armazena o sumário final na sessão para exibição imediata
            $_SESSION['last_import_result'] = $result;
            $_SESSION['last_import_result']['kind'] = $kind;
            $_SESSION['last_import_result']['return_to'] = $staging['return_to'] ?? $this->normalizeReturnContext(null, $kind);
            $_SESSION['last_import_result']['return_path'] = $staging['return_path'] ?? $this->returnPathForContext($_SESSION['last_import_result']['return_to']);

            SessionManager::flash('success', sprintf(
                'Lote %s concluído: %d inseridos, %d atualizados, %d ignorados, %d inválidos.',
                $result['batch_code'],
                $result['created_count'],
                $result['updated_count'],
                $result['skipped_count'],
                $result['error_count']
            ));

            return $response->withHeader('Location', '/leads/import?result=1')->withStatus(302);
        } catch (Throwable $e) {
            SessionManager::flash('error', 'Erro durante a importação: ' . $e->getMessage());
            return $response->withHeader('Location', '/leads/import')->withStatus(302);
        }
    }

    /**
     * Cancela o fluxo, remove arquivos temporários e retorna à listagem de leads.
     */
    public function cancel(Request $request, Response $response): Response
    {
        $staging = $_SESSION['import_staging'] ?? null;
        $returnPath = $staging['return_path'] ?? $this->returnPathForContext($staging['return_to'] ?? $this->normalizeReturnContext(null, $staging['dataset'] ?? 'leads'));
        if ($staging !== null && !empty($staging['id'])) {
            $this->importService->cancelStaging($staging['id']);
            unset($_SESSION['import_staging']);
        }

        SessionManager::flash('info', 'Processo de importação cancelado. Os arquivos e prévias temporárias foram descartados.');
        return $response->withHeader('Location', $returnPath)->withStatus(302);
    }

    private function normalizeReturnContext(mixed $context, string $dataset): string
    {
        if (in_array($context, ['leads', 'properties', 'owners'], true)) return $context;
        return in_array($dataset, ['leads', 'properties', 'owners'], true) ? $dataset : 'leads';
    }

    private function usesMultiDatasetImport(string $kind, array $staging, array $mapping): bool
    {
        if ($kind !== 'leads' || isset($mapping['first_name'])) return true;
        $headers = array_map(static fn($header) => mb_strtolower(trim((string)$header)), $staging['headers'] ?? []);
        return in_array('codigo_imovel', $headers, true);
    }

    private function returnPathForContext(string $context): string
    {
        return match ($context) {
            'properties' => '/properties',
            'owners' => '/owners',
            default => '/leads',
        };
    }

    private function importUrlForDataset(string $dataset, string $returnTo): string
    {
        return '/leads/import?' . http_build_query(['dataset' => $dataset, 'return_to' => $returnTo]);
    }
}
