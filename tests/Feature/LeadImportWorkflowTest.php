<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Application;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Database\Migrator;
use App\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

final class LeadImportWorkflowTest extends TestCase
{
    private string $tempDb;
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_import_feat_' . uniqid() . '.sqlite';
        Connection::setPath($this->tempDb);

        $migrator = new Migrator();
        $migrator->migrate();

        $this->pdo = Connection::getPdo();

        $hash = password_hash('SenhaSegura123!', PASSWORD_DEFAULT);
        $this->pdo->exec("
            INSERT INTO users (id, name, email, password_hash, role_id, is_active) VALUES
            (1, 'Gestor Imobiliária', 'admin@pinciara.com', '{$hash}', 1, 1),
            (2, 'Carlos Corretor', 'carlos@pinciara.com', '{$hash}', 2, 1);
        ");

        $this->app = Application::create();

        SessionManager::start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        SessionManager::destroy();
        Connection::reset();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    private function authenticateAs(int $userId, string $name, string $role, string $roleLabel): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = strtolower($name) . '@pinciara.com';
        $_SESSION['user_role'] = $role;
        $_SESSION['user_role_label'] = $roleLabel;
    }

    private function createUploadedFile(string $content, string $filename = 'leads.csv'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_feat_');
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

    public function testGuestIsRedirectedToLogin(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/leads/import');
        $response = $this->app->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testCorretorIsForbiddenFromImportRoutes(): void
    {
        $this->authenticateAs(2, 'Carlos Corretor', 'corretor', 'Corretor');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/leads/import');
        $response = $this->app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAdminCanAccessImportWizard(): void
    {
        $this->authenticateAs(1, 'Gestor Imobiliária', 'admin', 'Administrador');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/leads/import');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Assistente de Importação de Planilhas', $body);
        $this->assertStringContainsString('Upload do CSV', $body);
    }

    public function testCompleteImportFlowFromUploadToConfirmation(): void
    {
        $this->authenticateAs(1, 'Gestor Imobiliária', 'admin', 'Administrador');
        $csrfToken = Csrf::getToken();

        // 1. Passo 1: Upload do CSV
        $csvContent = "Nome;Telefone;E-mail;Origem;Notas\n" .
            "Mariana Souza;21999991111;mariana@teste.com;site;Interesse cobertura\n" .
            "Bruno Mendes;21988882222;bruno@teste.com;whatsapp;Procura 2 quartos\n";

        $file = $this->createUploadedFile($csvContent, 'clientes_novos.csv');

        $uploadRequest = (new ServerRequestFactory())->createServerRequest('POST', '/leads/import/upload')
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['_csrf_token' => $csrfToken]);

        $uploadResponse = $this->app->handle($uploadRequest);
        $this->assertSame(302, $uploadResponse->getStatusCode());
        $this->assertSame('/leads/import', $uploadResponse->getHeaderLine('Location'));

        // Verifica que o staging foi gravado na sessão
        $this->assertNotEmpty($_SESSION['import_staging']);
        $this->assertSame(2, $_SESSION['import_staging']['step']);
        $this->assertSame('clientes_novos.csv', $_SESSION['import_staging']['filename']);

        // 2. Passo 2: Envio de Mapeamento e Validação (Dry-run)
        $validateRequest = (new ServerRequestFactory())->createServerRequest('POST', '/leads/import/validate')
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
                'mapping' => [
                    'name' => 0,
                    'phone' => 1,
                    'email' => 2,
                    'source' => 3,
                    'notes' => 4,
                ],
                'duplicate_policy' => 'merge',
            ]);

        $validateResponse = $this->app->handle($validateRequest);
        $this->assertSame(302, $validateResponse->getStatusCode());

        // Verifica que a validação foi armazenada na sessão
        $this->assertSame(3, $_SESSION['import_staging']['step']);
        $this->assertNotNull($_SESSION['import_staging']['validation']);
        $this->assertSame(2, $_SESSION['import_staging']['validation']['new_count']);
        $this->assertSame(0, $_SESSION['import_staging']['validation']['error_count']);

        // 3. Passo 3: Confirmação e Execução Atômica
        $confirmRequest = (new ServerRequestFactory())->createServerRequest('POST', '/leads/import/confirm')
            ->withParsedBody([
                '_csrf_token' => $csrfToken,
            ]);

        $confirmResponse = $this->app->handle($confirmRequest);
        $this->assertSame(302, $confirmResponse->getStatusCode());
        $this->assertStringContainsString('/leads/import?result=1', $confirmResponse->getHeaderLine('Location'));

        // Verifica que a sessão de staging foi limpa
        $this->assertArrayNotHasKey('import_staging', $_SESSION);
        $this->assertNotEmpty($_SESSION['last_import_result']);
        $this->assertSame(2, $_SESSION['last_import_result']['created_count']);

        // Verifica que os leads foram persistidos no banco
        $leadsCount = (int)$this->pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
        $this->assertSame(2, $leadsCount);

        // Verifica o lote gravado na tabela import_batches
        $batch = $this->pdo->query('SELECT * FROM import_batches')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($batch);
        $this->assertSame('completed', $batch['status']);
        $this->assertSame(2, (int)$batch['created_count']);

        // 4. Acesso à tela de conclusão
        $resultRequest = (new ServerRequestFactory())->createServerRequest('GET', '/leads/import?result=1');
        $resultResponse = $this->app->handle($resultRequest);
        $this->assertSame(200, $resultResponse->getStatusCode());
        $resultBody = (string)$resultResponse->getBody();
        $this->assertStringContainsString('Importação Concluída com Sucesso', $resultBody);
        $this->assertStringContainsString($batch['batch_code'], $resultBody);
    }

    public function testCancelFlowDiscardsStagingFile(): void
    {
        $this->authenticateAs(1, 'Gestor Imobiliária', 'admin', 'Administrador');
        $csrfToken = Csrf::getToken();

        $file = $this->createUploadedFile("Nome\nTeste\n");

        $uploadRequest = (new ServerRequestFactory())->createServerRequest('POST', '/leads/import/upload')
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['_csrf_token' => $csrfToken]);

        $this->app->handle($uploadRequest);
        $this->assertNotEmpty($_SESSION['import_staging']);

        // Cancela o fluxo
        $cancelRequest = (new ServerRequestFactory())->createServerRequest('POST', '/leads/import/cancel')
            ->withParsedBody(['_csrf_token' => $csrfToken]);

        $cancelResponse = $this->app->handle($cancelRequest);
        $this->assertSame(302, $cancelResponse->getStatusCode());
        $this->assertSame('/leads', $cancelResponse->getHeaderLine('Location'));

        // Staging limpo
        $this->assertArrayNotHasKey('import_staging', $_SESSION);
    }
}
