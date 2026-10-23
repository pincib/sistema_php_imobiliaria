<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database\Connection;
use App\Database\Migrator;
use App\Modules\Leads\Repositories\ImportBatchRepository;
use App\Modules\Leads\Repositories\LeadRepository;
use App\Modules\Leads\Services\LeadImportService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

final class LeadImportServiceTest extends TestCase
{
    private string $tempDb;
    private string $tempStorageDir;
    private PDO $pdo;
    private LeadImportService $service;
    private ImportBatchRepository $batchRepo;
    private LeadRepository $leadRepo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_import_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();

        // Usuário admin para testes
        $hash = password_hash('SenhaSegura123!', PASSWORD_DEFAULT);
        $this->pdo->exec("
            INSERT INTO users (id, name, email, password_hash, role_id, is_active) VALUES
            (1, 'Administrador Teste', 'admin@teste.com', '{$hash}', 1, 1);
        ");

        $this->tempStorageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_storage_' . uniqid();
        @mkdir($this->tempStorageDir, 0755, true);

        $this->batchRepo = new ImportBatchRepository($this->pdo);
        $this->leadRepo = new LeadRepository($this->pdo);
        $this->service = new LeadImportService(
            $this->tempStorageDir,
            $this->batchRepo,
            $this->leadRepo
        );
    }

    protected function tearDown(): void
    {
        Connection::reset();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }

        // Limpa arquivos do diretório de testes
        if (is_dir($this->tempStorageDir)) {
            $files = glob($this->tempStorageDir . DIRECTORY_SEPARATOR . '*');
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
            @rmdir($this->tempStorageDir);
        }
    }

    private function createUploadedFile(string $content, string $filename = 'leads.csv'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempPath, $content);

        $stream = (new StreamFactory())->createStreamFromFile($tempPath);

        return new UploadedFile(
            $stream,
            $filename,
            'text/csv',
            filesize($tempPath) ?: 0,
            UPLOAD_ERR_OK
        );
    }

    public function testStageUploadValidCsvAndSuggestsMapping(): void
    {
        $csvContent = "Nome;Telefone;E-mail;Origem;Observações\n" .
            "Beatriz Mendes;21988887777;beatriz@teste.com;site;Interesse no AP101\n" .
            "Rodrigo Silva;21977776666;rodrigo@teste.com;whatsapp;Contato inicial\n";

        $file = $this->createUploadedFile($csvContent, 'clientes_antigos.csv');
        $staged = $this->service->stageUpload($file);

        $this->assertNotEmpty($staged['staging_id']);
        $this->assertSame('clientes_antigos.csv', $staged['filename']);
        $this->assertSame(';', $staged['delimiter']);
        $this->assertSame(['Nome', 'Telefone', 'E-mail', 'Origem', 'Observações'], $staged['headers']);
        $this->assertCount(2, $staged['preview_rows']);
        $this->assertSame(2, $staged['total_rows_estimate']);

        // Mapeamento sugerido
        $this->assertSame(0, $staged['suggested_mapping']['name']);
        $this->assertSame(1, $staged['suggested_mapping']['phone']);
        $this->assertSame(2, $staged['suggested_mapping']['email']);
        $this->assertSame(3, $staged['suggested_mapping']['source']);
        $this->assertSame(4, $staged['suggested_mapping']['notes']);

        // Arquivo físico existe no diretório temporário
        $stagingPath = $this->service->getStagingPath($staged['staging_id']);
        $this->assertFileExists($stagingPath);
    }

    public function testStageUploadRejectsNonCsvFiles(): void
    {
        $file = $this->createUploadedFile('binario', 'arquivo.pdf');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Formato de arquivo inválido');
        $this->service->stageUpload($file);
    }

    public function testValidateBatchIdentifiesDuplicatesAndErrors(): void
    {
        // 1. Cadastra um lead pré-existente no banco
        $this->leadRepo->create([
            'name' => 'Cliente Existente',
            'phone' => '21988887777',
            'email' => 'existente@teste.com',
            'source' => 'manual',
        ]);

        $csvContent = "Nome;Telefone;E-mail;Origem;Observações\n" .
            "Novo Lead 1;21911112222;novo1@teste.com;site;Nota 1\n" .
            ";21922223333;semnome@teste.com;site;Sem nome\n" . // Erro: nome vazio
            "Cliente Duplicado Fone;21988887777;outro@teste.com;portal;Fone repetido\n" . // Duplicado no DB (fone)
            "Cliente Email Invalido;21933334444;email-invalido;site;Email ruim\n" . // Erro: email inválido
            "Novo Lead 2;21944445555;novo2@teste.com;indicacao;Nota 2\n";

        $file = $this->createUploadedFile($csvContent);
        $staged = $this->service->stageUpload($file);

        $mapping = [
            'name' => 0,
            'phone' => 1,
            'email' => 2,
            'source' => 3,
            'notes' => 4,
        ];

        $val = $this->service->validateBatch($staged['staging_id'], $mapping);

        $this->assertSame(5, $val['total_rows']);
        $this->assertSame(2, $val['new_count']); // Novo 1 e Novo 2
        $this->assertSame(1, $val['duplicate_count']); // Cliente Duplicado Fone
        $this->assertSame(2, $val['error_count']); // Sem nome (linha 3) e Email ruim (linha 5)

        // Verifica erros identificados com número da linha
        $this->assertCount(2, $val['errors_list']);
        $this->assertSame(3, $val['errors_list'][0]['row']);
        $this->assertStringContainsString('Nome', $val['errors_list'][0]['reason']);

        $this->assertSame(5, $val['errors_list'][1]['row']);
        $this->assertStringContainsString('E-mail em formato inválido', $val['errors_list'][1]['reason']);
    }

    public function testExecuteImportWithMergePolicyCreatesBatchAndMergesDuplicates(): void
    {
        // 1. Cria lead existente no banco sem notas
        $existingId = $this->leadRepo->create([
            'name' => 'Cliente Existente',
            'phone' => '21988887777',
            'email' => 'existente@teste.com',
            'source' => 'manual',
            'notes' => 'Nota original',
        ]);

        $csvContent = "Nome;Telefone;E-mail;Origem;Observações\n" .
            "Lead Novo A;21911110000;novoa@teste.com;site;Nota Nova\n" .
            "Cliente Existente;21988887777;existente@teste.com;campanha_antiga;Dado Atualizado\n";

        $file = $this->createUploadedFile($csvContent);
        $staged = $this->service->stageUpload($file);

        $mapping = [
            'name' => 0,
            'phone' => 1,
            'email' => 2,
            'source' => 3,
            'notes' => 4,
        ];

        $result = $this->service->executeImport(
            $staged['staging_id'],
            $mapping,
            LeadImportService::POLICY_MERGE,
            actorUserId: 1
        );

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['total_rows']);
        $this->assertSame(1, $result['created_count']);
        $this->assertSame(1, $result['updated_count']);
        $this->assertSame(0, $result['skipped_count']);
        $this->assertNotEmpty($result['batch_code']);

        // Verifica se o lote foi gravado no banco de dados
        $batch = $this->batchRepo->findByCode($result['batch_code']);
        $this->assertNotNull($batch);
        $this->assertSame('completed', $batch['status']);
        $this->assertSame(1, (int)$batch['created_count']);
        $this->assertSame(1, (int)$batch['updated_count']);

        // Verifica se a interação de auditoria foi inserida para o lead mesclado
        $stmtInt = $this->pdo->prepare('SELECT * FROM lead_interactions WHERE lead_id = :lead_id');
        $stmtInt->execute([':lead_id' => $existingId]);
        $interactions = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $interactions);
        $this->assertStringContainsString($result['batch_code'], $interactions[0]['notes']);

        // Verifica que o arquivo físico de staging foi expurgado
        $stagingPath = $this->service->getStagingPath($staged['staging_id']);
        $this->assertFileDoesNotExist($stagingPath);
    }

    public function testExecuteImportWithSkipPolicyIgnoresDuplicates(): void
    {
        $this->leadRepo->create([
            'name' => 'Cliente Existente',
            'phone' => '21988887777',
            'email' => 'existente@teste.com',
            'source' => 'manual',
        ]);

        $csvContent = "Nome;Telefone;E-mail;Origem;Observações\n" .
            "Lead Novo B;21922220000;novob@teste.com;site;Nota\n" .
            "Cliente Repetido;21988887777;existente@teste.com;site;Ignorar\n";

        $file = $this->createUploadedFile($csvContent);
        $staged = $this->service->stageUpload($file);

        $mapping = ['name' => 0, 'phone' => 1, 'email' => 2, 'source' => 3, 'notes' => 4];

        $result = $this->service->executeImport(
            $staged['staging_id'],
            $mapping,
            LeadImportService::POLICY_SKIP,
            actorUserId: 1
        );

        $this->assertSame(1, $result['created_count']);
        $this->assertSame(0, $result['updated_count']);
        $this->assertSame(1, $result['skipped_count']);
    }

    public function testReexecutionOfSameBatchIsDetectedViaSha256(): void
    {
        $csvContent = "Nome;Telefone;E-mail\nLucas Rocha;21966665555;lucas@teste.com\n";
        $file1 = $this->createUploadedFile($csvContent);
        $staged1 = $this->service->stageUpload($file1);

        $mapping = ['name' => 0, 'phone' => 1, 'email' => 2];
        $this->service->executeImport($staged1['staging_id'], $mapping, LeadImportService::POLICY_MERGE, 1);

        // Upload do mesmo arquivo idêntico
        $file2 = $this->createUploadedFile($csvContent);
        $staged2 = $this->service->stageUpload($file2);

        // Deve detectar o lote anterior
        $this->assertNotNull($staged2['previous_batch']);

        // Tentativa de executar sem confirmação deve disparar exceção
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('já foi importado no lote');
        $this->service->executeImport(
            $staged2['staging_id'],
            $mapping,
            LeadImportService::POLICY_MERGE,
            actorUserId: 1,
            allowDuplicateBatch: false
        );
    }

    public function testCancelStagingRemovesTemporaryFile(): void
    {
        $file = $this->createUploadedFile("Nome\nTeste\n");
        $staged = $this->service->stageUpload($file);

        $stagingPath = $this->service->getStagingPath($staged['staging_id']);
        $this->assertFileExists($stagingPath);

        $cancelled = $this->service->cancelStaging($staged['staging_id']);
        $this->assertTrue($cancelled);
        $this->assertFileDoesNotExist($stagingPath);
    }

    public function testExecuteImportRejectsFilesAboveRowLimitWithoutPartialWrites(): void
    {
        $rows = ["Nome;Telefone"];
        for ($i = 1; $i <= LeadImportService::MAX_ROWS + 1; $i++) {
            $rows[] = "Lead {$i};219" . str_pad((string)$i, 8, '0', STR_PAD_LEFT);
        }

        $staged = $this->service->stageUpload($this->createUploadedFile(implode("\n", $rows)));

        try {
            $this->service->executeImport(
                $staged['staging_id'],
                ['name' => 0, 'phone' => 1],
                LeadImportService::POLICY_MERGE,
                actorUserId: 1
            );
            $this->fail('A importação deveria rejeitar mais de 5.000 linhas sem gravar um lote parcial.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('5000', $exception->getMessage());
        }

        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn());
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM import_batches')->fetchColumn());
    }
}
