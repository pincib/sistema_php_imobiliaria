<?php

declare(strict_types=1);

namespace App\Modules\Leads\Services;

use App\Audit\AuditService;
use App\Database\Connection;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Leads\Repositories\ImportBatchRepository;
use App\Modules\Leads\Repositories\InteractionRepository;
use App\Modules\Leads\Repositories\LeadRepository;
use App\Modules\Leads\Support\LeadNormalizer;
use InvalidArgumentException;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class LeadImportService
{
    public const MAX_FILE_SIZE = 5242880; // 5 MB
    public const MAX_ROWS = 5000;

    public const POLICY_MERGE = 'merge';
    public const POLICY_SKIP = 'skip';

    private string $storageDir;
    private ImportBatchRepository $batchRepository;
    private LeadRepository $leadRepository;
    private InteractionRepository $interactionRepository;

    public function __construct(
        ?string $storageDir = null,
        ?ImportBatchRepository $batchRepository = null,
        ?LeadRepository $leadRepository = null,
        ?InteractionRepository $interactionRepository = null
    ) {
        $this->storageDir = $storageDir ?? dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }

        $this->batchRepository = $batchRepository ?? new ImportBatchRepository();
        $this->leadRepository = $leadRepository ?? new LeadRepository();
        $this->interactionRepository = $interactionRepository ?? new InteractionRepository();
    }

    /**
     * Valida o arquivo enviado e o armazena em staging temporário para o assistente.
     *
     * @return array{
     *     staging_id: string,
     *     filename: string,
     *     file_size: int,
     *     file_hash: string,
     *     delimiter: string,
     *     headers: list<string>,
     *     preview_rows: list<list<string>>,
     *     total_rows_estimate: int,
     *     suggested_mapping: array<string, int>,
     *     previous_batch: ?array<string, mixed>
     * }
     */
    public function stageUpload(UploadedFileInterface $file): array
    {
        // 1. Limpeza preventiva de arquivos temporários antigos (> 24h)
        $this->purgeOldStagingFiles();

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Erro no envio do arquivo. Verifique o arquivo e tente novamente.');
        }

        $fileSize = $file->getSize() ?? 0;
        if ($fileSize <= 0) {
            throw new InvalidArgumentException('O arquivo enviado está vazio.');
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(sprintf(
                'O arquivo excede o limite máximo permitido de %d MB.',
                (int)(self::MAX_FILE_SIZE / 1048576)
            ));
        }

        $clientFilename = $file->getClientFilename() ?? 'planilha.csv';
        $ext = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            throw new InvalidArgumentException('Formato de arquivo inválido. Por favor, envie uma planilha no formato CSV (.csv).');
        }

        // 2. Grava em arquivo de staging protegido
        $stagingId = bin2hex(random_bytes(16));
        $stagingPath = $this->getStagingPath($stagingId);

        $file->moveTo($stagingPath);

        // 3. Calcula hash SHA-256 e verifica duplicidade de lote
        $fileHash = hash_file('sha256', $stagingPath);
        if ($fileHash === false) {
            @unlink($stagingPath);
            throw new RuntimeException('Falha ao calcular hash de integridade do arquivo.');
        }

        $previousBatch = $this->batchRepository->findLatestByHash($fileHash);

        // 4. Analisa delimitador e codificação
        $delimiter = $this->detectDelimiter($stagingPath);
        $parsed = $this->readCsvSample($stagingPath, $delimiter, 5);

        if (empty($parsed['headers'])) {
            @unlink($stagingPath);
            throw new InvalidArgumentException('Não foi possível identificar o cabeçalho da planilha. Verifique se o arquivo CSV é válido.');
        }

        // 5. Mapeamento heurístico sugerido
        $suggestedMapping = $this->suggestMapping($parsed['headers']);

        return [
            'staging_id' => $stagingId,
            'filename' => $clientFilename,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'delimiter' => $delimiter,
            'headers' => $parsed['headers'],
            'preview_rows' => $parsed['rows'],
            'total_rows_estimate' => $parsed['total_rows'],
            'suggested_mapping' => $suggestedMapping,
            'previous_batch' => $previousBatch,
        ];
    }

    /** Stage one of the three bundled source CSVs through a strict filename allowlist. */
    public function stageBundledFile(string $filename): array
    {
        $allowed = ['contacts_filtrados.csv', 'Imóveis-20261020.csv', 'Planilha sem título - Proprietários_por_imóveis-20261023 (2).csv'];
        if (!in_array($filename, $allowed, true)) throw new InvalidArgumentException('Selecione uma das fontes CSV disponíveis.');
        $source = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'planilhas_csv_import' . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($source) || !is_readable($source)) throw new InvalidArgumentException('O CSV de origem não está disponível no servidor.');
        $size = filesize($source) ?: 0;
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) throw new InvalidArgumentException('O CSV de origem está vazio ou excede o limite de 5 MB.');
        $this->purgeOldStagingFiles();
        $id = bin2hex(random_bytes(16)); $path = $this->getStagingPath($id);
        if (!copy($source, $path)) throw new RuntimeException('Não foi possível preparar o CSV para a prévia.');
        $hash = hash_file('sha256', $path); if ($hash === false) { @unlink($path); throw new RuntimeException('Falha ao calcular hash do CSV.'); }
        $delimiter = $this->detectDelimiter($path); $parsed = $this->readCsvSample($path, $delimiter, 5);
        if (empty($parsed['headers'])) { @unlink($path); throw new InvalidArgumentException('O CSV não possui cabeçalho legível.'); }
        return ['staging_id'=>$id,'filename'=>$filename,'file_size'=>$size,'file_hash'=>$hash,'delimiter'=>$delimiter,'headers'=>$parsed['headers'],'preview_rows'=>$parsed['rows'],'total_rows_estimate'=>$parsed['total_rows'],'suggested_mapping'=>$this->suggestMapping($parsed['headers']),'previous_batch'=>$this->batchRepository->findLatestByHash($hash)];
    }

    /**
     * Validação minuciosa de prévia e impacto (Dry-run).
     * Distingue deduplicação de identificação da regra de execução.
     *
     * @param array<string, int|string> $mapping
     * @return array{
     *     total_rows: int,
     *     new_count: int,
     *     duplicate_count: int,
     *     error_count: int,
     *     duplicate_policy: string,
     *     duplicates_sample: list<array<string, mixed>>,
     *     errors_list: list<array{row: int, name: string, reason: string, field: string}>,
     *     preview_mapped: list<array<string, mixed>>,
     *     has_name_mapped: bool
     * }
     */
    public function validateBatch(
        string $stagingId,
        array $mapping,
        string $duplicatePolicy = self::POLICY_MERGE,
        ?int $defaultBrokerId = null
    ): array {
        $stagingPath = $this->getStagingPath($stagingId);
        if (!file_exists($stagingPath)) {
            throw new InvalidArgumentException('Arquivo da prévia expirou ou não foi encontrado. Por favor, reenvie a planilha.');
        }

        $delimiter = $this->detectDelimiter($stagingPath);
        $handle = fopen($stagingPath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo temporário para validação.');
        }

        // Lê cabeçalho
        $headerLine = fgetcsv($handle, 0, $delimiter, '"', "\\");
        if ($headerLine === false) {
            fclose($handle);
            throw new InvalidArgumentException('Planilha vazia ou com cabeçalho ilegível.');
        }

        $nameCol = isset($mapping['name']) && $mapping['name'] !== '' ? (int)$mapping['name'] : null;
        if ($nameCol === null) {
            fclose($handle);
            return [
                'total_rows' => 0,
                'new_count' => 0,
                'duplicate_count' => 0,
                'error_count' => 0,
                'duplicate_policy' => $duplicatePolicy,
                'duplicates_sample' => [],
                'errors_list' => [[
                    'row' => 1,
                    'name' => '—',
                    'reason' => 'A coluna com o Nome do lead é obrigatória e deve ser mapeada.',
                    'field' => 'name',
                ]],
                'preview_mapped' => [],
                'has_name_mapped' => false,
            ];
        }

        $phoneCol = isset($mapping['phone']) && $mapping['phone'] !== '' ? (int)$mapping['phone'] : null;
        $emailCol = isset($mapping['email']) && $mapping['email'] !== '' ? (int)$mapping['email'] : null;
        $sourceCol = isset($mapping['source']) && $mapping['source'] !== '' ? (int)$mapping['source'] : null;
        $notesCol = isset($mapping['notes']) && $mapping['notes'] !== '' ? (int)$mapping['notes'] : null;
        $brokerCol = isset($mapping['broker']) && $mapping['broker'] !== '' ? (int)$mapping['broker'] : null;

        $totalRows = 0;
        $newCount = 0;
        $duplicateCount = 0;
        $errorCount = 0;

        $duplicatesSample = [];
        $errorsList = [];
        $previewMapped = [];

        // Rastreamento intra-planilha
        $seenPhones = [];
        $seenEmails = [];

        $rowNumber = 1; // Linha 1 foi o cabeçalho

        while (($rawRow = fgetcsv($handle, 0, $delimiter, '"', "\\")) !== false) {
            $rowNumber++;
            // Ignora linhas totalmente vazias
            if ($this->isEmptyRow($rawRow)) {
                continue;
            }

            $totalRows++;
            if ($totalRows > self::MAX_ROWS) {
                $errorsList[] = [
                    'row' => $rowNumber,
                    'name' => '—',
                    'reason' => sprintf('Limite excedido: a planilha possui mais de %d linhas.', self::MAX_ROWS),
                    'field' => 'file',
                ];
                $errorCount++;
                break;
            }

            // Extrai valores mapeados
            $rawName = isset($rawRow[$nameCol]) ? trim((string)$rawRow[$nameCol]) : '';
            $rawPhone = $phoneCol !== null && isset($rawRow[$phoneCol]) ? trim((string)$rawRow[$phoneCol]) : null;
            $rawEmail = $emailCol !== null && isset($rawRow[$emailCol]) ? trim((string)$rawRow[$emailCol]) : null;
            $rawSource = $sourceCol !== null && isset($rawRow[$sourceCol]) ? trim((string)$rawRow[$sourceCol]) : 'importacao_csv';
            $rawNotes = $notesCol !== null && isset($rawRow[$notesCol]) ? trim((string)$rawRow[$notesCol]) : null;

            // Validação de Nome
            if ($rawName === '') {
                $errorsList[] = [
                    'row' => $rowNumber,
                    'name' => '—',
                    'reason' => 'Nome do lead em branco ou não informado.',
                    'field' => 'name',
                ];
                $errorCount++;
                continue;
            }
            $cleanName = LeadNormalizer::sanitizeName($rawName);

            // Validação e normalização de Telefone
            $cleanPhone = null;
            if (!empty($rawPhone)) {
                $cleanPhone = LeadNormalizer::normalizePhone($rawPhone);
                if ($cleanPhone !== null && (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 13)) {
                    $errorsList[] = [
                        'row' => $rowNumber,
                        'name' => $cleanName,
                        'reason' => "Telefone com formato inconsistente ('{$rawPhone}').",
                        'field' => 'phone',
                    ];
                    $errorCount++;
                    continue;
                }
            }

            // Validação e normalização de E-mail
            $cleanEmail = null;
            if (!empty($rawEmail)) {
                $cleanEmail = LeadNormalizer::normalizeEmail($rawEmail);
                if ($cleanEmail !== null && !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                    $errorsList[] = [
                        'row' => $rowNumber,
                        'name' => $cleanName,
                        'reason' => "E-mail em formato inválido ('{$rawEmail}').",
                        'field' => 'email',
                    ];
                    $errorCount++;
                    continue;
                }
            }

            // Verificação de duplicidade: no banco de dados e na própria planilha
            $existingLead = null;
            $duplicateReason = null;

            if ($cleanPhone !== null) {
                if (isset($seenPhones[$cleanPhone])) {
                    $duplicateReason = "Telefone duplicado na própria planilha (linha {$seenPhones[$cleanPhone]}).";
                } else {
                    $found = $this->leadRepository->findByPhone($cleanPhone);
                    if ($found) {
                        $existingLead = $found;
                        $duplicateReason = "Telefone já cadastrado no CRM (Lead #{$found['id']} - {$found['name']}).";
                    }
                }
            }

            if ($existingLead === null && $duplicateReason === null && $cleanEmail !== null) {
                if (isset($seenEmails[$cleanEmail])) {
                    $duplicateReason = "E-mail duplicado na própria planilha (linha {$seenEmails[$cleanEmail]}).";
                } else {
                    $found = $this->leadRepository->findByEmail($cleanEmail);
                    if ($found) {
                        $existingLead = $found;
                        $duplicateReason = "E-mail já cadastrado no CRM (Lead #{$found['id']} - {$found['name']}).";
                    }
                }
            }

            if ($cleanPhone !== null) {
                $seenPhones[$cleanPhone] = $rowNumber;
            }
            if ($cleanEmail !== null) {
                $seenEmails[$cleanEmail] = $rowNumber;
            }

            $mappedRow = [
                'row' => $rowNumber,
                'name' => $cleanName,
                'phone' => $cleanPhone,
                'email' => $cleanEmail,
                'source' => $rawSource ?: 'importacao_csv',
                'notes' => $rawNotes,
                'is_duplicate' => $duplicateReason !== null,
                'duplicate_reason' => $duplicateReason,
                'existing_lead_id' => $existingLead['id'] ?? null,
            ];

            if ($duplicateReason !== null) {
                $duplicateCount++;
                if (count($duplicatesSample) < 10) {
                    $duplicatesSample[] = $mappedRow;
                }
            } else {
                $newCount++;
            }

            if (count($previewMapped) < 5) {
                $previewMapped[] = $mappedRow;
            }
        }

        fclose($handle);

        return [
            'total_rows' => $totalRows,
            'new_count' => $newCount,
            'duplicate_count' => $duplicateCount,
            'error_count' => $errorCount,
            'duplicate_policy' => $duplicatePolicy,
            'duplicates_sample' => $duplicatesSample,
            'errors_list' => $errorsList,
            'preview_mapped' => $previewMapped,
            'has_name_mapped' => true,
        ];
    }

    /**
     * Executa a importação em lote com integridade transacional garantida (tudo ou nada).
     *
     * @param array<string, int|string> $mapping
     * @return array{
     *     success: bool,
     *     batch_code: string,
     *     batch_id: int,
     *     total_rows: int,
     *     created_count: int,
     *     updated_count: int,
     *     skipped_count: int,
     *     error_count: int,
     *     message: string
     * }
     */
    public function executeImport(
        string $stagingId,
        array $mapping,
        string $duplicatePolicy,
        int $actorUserId,
        ?int $defaultBrokerId = null,
        bool $allowDuplicateBatch = false
    ): array {
        $stagingPath = $this->getStagingPath($stagingId);
        if (!file_exists($stagingPath)) {
            throw new InvalidArgumentException('Arquivo temporário expirou ou foi cancelado. Inicie um novo envio.');
        }

        $fileSize = filesize($stagingPath) ?: 0;
        $fileHash = hash_file('sha256', $stagingPath);
        if ($fileHash === false) {
            throw new RuntimeException('Falha ao validar integridade do arquivo.');
        }

        // Validação de reexecução de mesmo lote
        $previousBatch = $this->batchRepository->findLatestByHash($fileHash);
        if ($previousBatch !== null && !$allowDuplicateBatch) {
            throw new InvalidArgumentException(sprintf(
                'Este arquivo já foi importado no lote %s. Marque a confirmação para reprocessá-lo.',
                $previousBatch['batch_code']
            ));
        }

        $nameCol = isset($mapping['name']) && $mapping['name'] !== '' ? (int)$mapping['name'] : null;
        if ($nameCol === null) {
            throw new InvalidArgumentException('A coluna de Nome do lead deve estar obrigatoriamente mapeada.');
        }

        $phoneCol = isset($mapping['phone']) && $mapping['phone'] !== '' ? (int)$mapping['phone'] : null;
        $emailCol = isset($mapping['email']) && $mapping['email'] !== '' ? (int)$mapping['email'] : null;
        $sourceCol = isset($mapping['source']) && $mapping['source'] !== '' ? (int)$mapping['source'] : null;
        $notesCol = isset($mapping['notes']) && $mapping['notes'] !== '' ? (int)$mapping['notes'] : null;

        $delimiter = $this->detectDelimiter($stagingPath);
        $batchCode = 'IMP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        // Execução Atômica via Transação curta do SQLite
        $result = Connection::transaction(function (PDO $pdo) use (
            $stagingPath,
            $delimiter,
            $nameCol,
            $phoneCol,
            $emailCol,
            $sourceCol,
            $notesCol,
            $batchCode,
            $actorUserId,
            $defaultBrokerId,
            $duplicatePolicy,
            $fileHash,
            $fileSize
        ): array {
            $handle = fopen($stagingPath, 'r');
            if ($handle === false) {
                throw new RuntimeException('Falha ao abrir planilha para gravação.');
            }

            // Ignora cabeçalho
            fgetcsv($handle, 0, $delimiter, '"', "\\");

            // Cria o registro inicial do lote
            $batchId = $this->batchRepository->create([
                'batch_code' => $batchCode,
                'user_id' => $actorUserId,
                'filename' => basename($stagingPath),
                'file_hash' => $fileHash,
                'file_size' => $fileSize,
                'duplicate_policy' => $duplicatePolicy,
                'status' => 'processing',
            ]);

            $totalRows = 0;
            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $errors = [];

            // Statements preparados para máxima performance e segurança
            $stmtInsertLead = $pdo->prepare('
                INSERT INTO leads (
                    name, email, phone, source, stage, user_id, notes, import_batch_id, created_at, updated_at
                ) VALUES (
                    :name, :email, :phone, :source, :stage, :user_id, :notes, :import_batch_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');

            $stmtUpdateLead = $pdo->prepare('
                UPDATE leads SET
                    email = COALESCE(email, :email),
                    phone = COALESCE(phone, :phone),
                    notes = CASE 
                        WHEN :notes IS NOT NULL AND notes IS NOT NULL THEN notes || CHAR(10) || :notes
                        WHEN :notes IS NOT NULL THEN :notes
                        ELSE notes 
                    END,
                    import_batch_id = :import_batch_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');

            $stmtInsertInteraction = $pdo->prepare('
                INSERT INTO lead_interactions (
                    lead_id, user_id, type, notes, contacted_at, created_at
                ) VALUES (
                    :lead_id, :user_id, :type, :notes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');

            $rowNumber = 1;

            while (($rawRow = fgetcsv($handle, 0, $delimiter, '"', "\\")) !== false) {
                $rowNumber++;
                if ($this->isEmptyRow($rawRow)) {
                    continue;
                }

                $totalRows++;
                if ($totalRows > self::MAX_ROWS) {
                    break;
                }

                $rawName = isset($rawRow[$nameCol]) ? trim((string)$rawRow[$nameCol]) : '';
                $rawPhone = $phoneCol !== null && isset($rawRow[$phoneCol]) ? trim((string)$rawRow[$phoneCol]) : null;
                $rawEmail = $emailCol !== null && isset($rawRow[$emailCol]) ? trim((string)$rawRow[$emailCol]) : null;
                $rawSource = $sourceCol !== null && isset($rawRow[$sourceCol]) ? trim((string)$rawRow[$sourceCol]) : 'importacao_csv';
                $rawNotes = $notesCol !== null && isset($rawRow[$notesCol]) ? trim((string)$rawRow[$notesCol]) : null;

                if ($rawName === '') {
                    $errorCount++;
                    $errors[] = ['row' => $rowNumber, 'reason' => 'Nome em branco'];
                    continue;
                }
                $cleanName = LeadNormalizer::sanitizeName($rawName);

                $cleanPhone = null;
                if (!empty($rawPhone)) {
                    $cleanPhone = LeadNormalizer::normalizePhone($rawPhone);
                    if ($cleanPhone !== null && (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 13)) {
                        $errorCount++;
                        $errors[] = ['row' => $rowNumber, 'reason' => 'Telefone com formato inconsistente'];
                        continue;
                    }
                }

                $cleanEmail = null;
                if (!empty($rawEmail)) {
                    $cleanEmail = LeadNormalizer::normalizeEmail($rawEmail);
                    if ($cleanEmail !== null && !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                        $errorCount++;
                        $errors[] = ['row' => $rowNumber, 'reason' => 'E-mail inválido'];
                        continue;
                    }
                }

                // Identificação de duplicidade existente
                $existingLead = null;
                if ($cleanPhone !== null) {
                    $existingLead = $this->leadRepository->findByPhone($cleanPhone);
                }
                if ($existingLead === null && $cleanEmail !== null) {
                    $existingLead = $this->leadRepository->findByEmail($cleanEmail);
                }

                if ($existingLead !== null) {
                    if ($duplicatePolicy === self::POLICY_MERGE) {
                        // Mescla informações mantendo os dados existentes e atualizando lacunas
                        $stmtUpdateLead->execute([
                            ':id' => $existingLead['id'],
                            ':email' => $cleanEmail,
                            ':phone' => $cleanPhone,
                            ':notes' => $rawNotes ? "[Lote {$batchCode}] {$rawNotes}" : null,
                            ':import_batch_id' => $batchId,
                        ]);

                        // Registra evento auditável de interação no histórico do lead
                        $interactionNotes = sprintf(
                            'Lead atualizado por importação da planilha [Lote: %s]. Origem informada: %s.',
                            $batchCode,
                            $rawSource ?: 'importacao_csv'
                        );
                        if ($rawNotes) {
                            $interactionNotes .= ' Obs: ' . $rawNotes;
                        }

                        $stmtInsertInteraction->execute([
                            ':lead_id' => $existingLead['id'],
                            ':user_id' => $actorUserId,
                            ':type' => 'importacao',
                            ':notes' => $interactionNotes,
                        ]);

                        $updatedCount++;
                    } else {
                        // Pular registros duplicados
                        $skippedCount++;
                    }
                } else {
                    // Novo lead
                    $assignedBroker = $defaultBrokerId ?? $actorUserId;
                    $stmtInsertLead->execute([
                        ':name' => $cleanName,
                        ':email' => $cleanEmail,
                        ':phone' => $cleanPhone,
                        ':source' => $rawSource ?: 'importacao_csv',
                        ':stage' => PipelineManager::STAGE_NOVO,
                        ':user_id' => $assignedBroker,
                        ':notes' => $rawNotes,
                        ':import_batch_id' => $batchId,
                    ]);
                    $createdCount++;
                }
            }

            fclose($handle);

            // Atualiza o lote com contagens consolidadas e status de conclusão
            $summary = [
                'total_rows' => $totalRows,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'errors_sample' => array_slice($errors, 0, 50),
            ];

            $this->batchRepository->update($batchId, [
                'total_rows' => $totalRows,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'error_count' => $errorCount,
                'status' => 'completed',
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            ]);

            // Auditoria da operação
            AuditService::log(
                'lead.import',
                'import_batch',
                (string)$batchId,
                null,
                [
                    'batch_code' => $batchCode,
                    'total_rows' => $totalRows,
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'skipped' => $skippedCount,
                    'errors' => $errorCount,
                    'policy' => $duplicatePolicy,
                ],
                $actorUserId
            );

            return [
                'success' => true,
                'batch_code' => $batchCode,
                'batch_id' => $batchId,
                'total_rows' => $totalRows,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'error_count' => $errorCount,
                'message' => 'Importação de planilha concluída com sucesso.',
            ];
        });

        // 6. Política de Retenção: Expurgar o arquivo físico temporário do disco logo após o término
        $this->cancelStaging($stagingId);

        return $result;
    }

    /**
     * Cancela e expurga o arquivo de staging do disco local.
     */
    public function cancelStaging(string $stagingId): bool
    {
        $path = $this->getStagingPath($stagingId);
        if (file_exists($path)) {
            return @unlink($path);
        }
        return false;
    }

    /**
     * Retorna o repositório de lotes de importação.
     */
    public function getBatchRepository(): ImportBatchRepository
    {
        return $this->batchRepository;
    }

    /**
     * Retorna o caminho absoluto do arquivo de staging temporário.
     */
    public function getStagingPath(string $stagingId): string
    {
        // Sanitiza ID para evitar path traversal
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $stagingId);
        return $this->storageDir . DIRECTORY_SEPARATOR . 'staging_' . $safeId . '.csv';
    }

    /**
     * Detecta o delimitador CSV mais provável lendo as primeiras linhas.
     */
    public function detectDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ';';
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return ';';
        }

        // Remove UTF-8 BOM se presente
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;

        $semicolons = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');
        $tabs = substr_count($firstLine, "\t");

        if ($tabs > $semicolons && $tabs > $commas) {
            return "\t";
        }
        if ($semicolons >= $commas) {
            return ';';
        }

        return ',';
    }

    /**
     * Lê cabeçalho e amostra das primeiras linhas com tratamento de codificação.
     *
     * @return array{headers: list<string>, rows: list<list<string>>, total_rows: int}
     */
    private function readCsvSample(string $filePath, string $delimiter, int $sampleLimit = 5): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $headers = [];
        $rows = [];
        $totalRows = 0;

        $firstRow = fgetcsv($handle, 0, $delimiter, '"', "\\");
        if ($firstRow !== false) {
            foreach ($firstRow as $idx => $h) {
                $val = trim((string)$h);
                // Remove BOM
                if ($idx === 0) {
                    $val = preg_replace('/^\xEF\xBB\xBF/', '', $val) ?? $val;
                }
                // Converte caracteres se necessário
                if (!mb_check_encoding($val, 'UTF-8')) {
                    $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
                }
                $headers[] = $val;
            }
        }

        while (($row = fgetcsv($handle, 0, $delimiter, '"', "\\")) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $totalRows++;
            if (count($rows) < $sampleLimit) {
                $cleanRow = [];
                foreach ($row as $col) {
                    $colVal = trim((string)$col);
                    if (!mb_check_encoding($colVal, 'UTF-8')) {
                        $colVal = mb_convert_encoding($colVal, 'UTF-8', 'ISO-8859-1');
                    }
                    $cleanRow[] = $colVal;
                }
                $rows[] = $cleanRow;
            }
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Mapeamento heurístico baseado no nome das colunas da planilha.
     *
     * @param list<string> $headers
     * @return array<string, int>
     */
    private function suggestMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $index => $header) {
            $norm = strtolower(trim($header));
            // Remove acentos
            $norm = strtr(
                $norm,
                ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']
            );

            if (!isset($mapping['name']) && preg_match('/^(nome|name|cliente|contato|lead)$/i', $norm)) {
                $mapping['name'] = $index;
            } elseif (!isset($mapping['phone']) && preg_match('/(telefone|celular|fone|tel|phone|whatsapp|zap)/i', $norm)) {
                $mapping['phone'] = $index;
            } elseif (!isset($mapping['email']) && preg_match('/(email|e-mail|correio)/i', $norm)) {
                $mapping['email'] = $index;
            } elseif (!isset($mapping['source']) && preg_match('/(origem|source|canal|meio)/i', $norm)) {
                $mapping['source'] = $index;
            } elseif (!isset($mapping['notes']) && preg_match('/(obs|observacao|observacoes|notas|nota|notes|detalhe)/i', $norm)) {
                $mapping['notes'] = $index;
            } elseif (!isset($mapping['broker']) && preg_match('/(corretor|responsavel|atendente|usuario|user)/i', $norm)) {
                $mapping['broker'] = $index;
            }
        }

        return $mapping;
    }

    /**
     * Verifica se uma linha CSV está totalmente vazia.
     *
     * @param list<string|null>|null $row
     */
    private function isEmptyRow(?array $row): bool
    {
        if (empty($row)) {
            return true;
        }
        foreach ($row as $cell) {
            if ($cell !== null && trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove arquivos de staging com mais de 24 horas.
     */
    private function purgeOldStagingFiles(int $maxAgeSeconds = 86400): int
    {
        $purged = 0;
        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . 'staging_*.csv');
        if ($files === false) {
            return 0;
        }

        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAgeSeconds) {
                if (@unlink($file)) {
                    $purged++;
                }
            }
        }

        return $purged;
    }
}
