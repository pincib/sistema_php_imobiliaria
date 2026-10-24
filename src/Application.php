<?php

declare(strict_types=1);

namespace App;

use App\Backup\BackupService;
use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\SessionMiddleware;
use App\Modules\Admin\Controllers\AuditController;
use App\Modules\Admin\Controllers\SystemController;
use App\Modules\Admin\Controllers\UserController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Leads\Controllers\LeadImportController;
use App\Modules\Leads\Controllers\LeadsController;
use App\Modules\Properties\Controllers\PropertiesController;
use App\Modules\Owners\Controllers\OwnersController;
use App\Modules\Reports\Controllers\ReportsController;
use App\Modules\Signboards\Controllers\SignboardsController;
use App\View\Renderer;
use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteCollectorProxy;

final class Application
{
    public static function create(?string $basePath = null): App
    {
        $basePath = $basePath ?? dirname(__DIR__);

        // Carrega .env
        if (file_exists($basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->safeLoad();
        }

        // Configuração de Fuso Horário Padrão da Aplicação
        $timezone = $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo';
        date_default_timezone_set($timezone);

        $app = AppFactory::create();

        // Middleware de parse de formulários e JSON do Slim
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // Middleware de Sessão Global
        $app->add(new SessionMiddleware());

        // Middleware de Tratamento de Erros Personalizado
        $errorMiddleware = $app->addErrorMiddleware(
            displayErrorDetails: filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            logErrors: true,
            logErrorDetails: true
        );

        $errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (Request $request, \Throwable $exception) {
            $response = new SlimResponse();
            $acceptsJson = str_contains($request->getHeaderLine('Accept'), 'application/json');

            if ($acceptsJson) {
                $response->getBody()->write(json_encode(['error' => 'Recurso não encontrado.'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $renderer = new Renderer();
            $response->getBody()->write($renderer->render('errors/404', [
                'title' => '404 — Página Não Encontrada',
            ]));
            return $response->withStatus(404);
        });

        // Rota de Saúde (Health Check)
        $app->get('/health', function (Request $request, Response $response) use ($basePath) {
            $storageDir = $basePath . DIRECTORY_SEPARATOR . 'storage';
            $dbStatus = 'connected';
            $integrity = 'unknown';

            try {
                $pdo = Connection::getPdo();
                $pdo->query('SELECT 1')->fetch();
                $integrity = (string)$pdo->query('PRAGMA integrity_check;')->fetchColumn();
            } catch (\Throwable $e) {
                $dbStatus = 'error: ' . $e->getMessage();
            }

            $freeBytes = @disk_free_space($storageDir);
            $totalBytes = @disk_total_space($storageDir);
            $usedPercent = ($freeBytes !== false && $totalBytes > 0)
                ? round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1)
                : null;

            $backupService = new BackupService();
            $latest = $backupService->getLatestBackup();

            $isHealthy = ($dbStatus === 'connected' && ($integrity === 'ok' || $integrity === 'unknown'));

            $payload = json_encode([
                'status' => $isHealthy ? 'ok' : 'degraded',
                'database' => $dbStatus,
                'integrity' => $integrity,
                'disk' => [
                    'free_bytes' => $freeBytes ?: 0,
                    'used_percent' => $usedPercent,
                ],
                'backup' => [
                    'latest_file' => $latest['filename'] ?? null,
                    'age_hours' => $latest['age_hours'] ?? null,
                ],
                'timestamp' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus($isHealthy ? 200 : 503);
        });

        // -------------------------------------------------------------
        // Rotas Públicas / Visitantes (Guest)
        // -------------------------------------------------------------
        $app->group('', function (RouteCollectorProxy $group) {
            $group->get('/login', [AuthController::class, 'showLoginForm']);
            $group->post('/login', [AuthController::class, 'login'])->add(new CsrfMiddleware());
        })->add(new GuestMiddleware());

        // -------------------------------------------------------------
        // Rotas Autenticadas (Corretor e Administrador)
        // -------------------------------------------------------------
        $app->group('', function (RouteCollectorProxy $group) {
            $group->post('/logout', [AuthController::class, 'logout'])->add(new CsrfMiddleware());

            $group->get('/', [DashboardController::class, 'index']);
            $group->get('/reports', [ReportsController::class, 'index'])->add(new RoleMiddleware(['admin']));
            $group->get('/reports/export.csv', [ReportsController::class, 'export'])->add(new RoleMiddleware(['admin']));
            $group->post('/follow-ups/{id:[0-9]+}/complete', [DashboardController::class, 'completeFollowUp'])->add(new CsrfMiddleware());
            $group->post('/follow-ups/{id:[0-9]+}/reschedule', [DashboardController::class, 'rescheduleFollowUp'])->add(new CsrfMiddleware());

            // Módulo de Leads e Pipeline
            // Assistente de Importação CSV (Restrito a Administradores)
            $group->group('/leads/import', function (RouteCollectorProxy $importGroup) {
                $importGroup->get('', [LeadImportController::class, 'index']);
                $importGroup->post('/upload', [LeadImportController::class, 'upload'])->add(new CsrfMiddleware());
                $importGroup->post('/validate', [LeadImportController::class, 'validate'])->add(new CsrfMiddleware());
                $importGroup->post('/back', [LeadImportController::class, 'backToMapping'])->add(new CsrfMiddleware());
                $importGroup->post('/confirm', [LeadImportController::class, 'confirm'])->add(new CsrfMiddleware());
                $importGroup->post('/cancel', [LeadImportController::class, 'cancel'])->add(new CsrfMiddleware());
            })->add(new RoleMiddleware(['admin']));

            $group->get('/leads', [LeadsController::class, 'index']);
            $group->get('/leads/new', [LeadsController::class, 'create']);
            $group->post('/leads', [LeadsController::class, 'store'])->add(new CsrfMiddleware());
            $group->get('/leads/{id:[0-9]+}', [LeadsController::class, 'show']);
            $group->get('/leads/{id:[0-9]+}/edit', [LeadsController::class, 'edit']);
            $group->get('/leads/{id:[0-9]+}/whatsapp', [LeadsController::class, 'whatsapp']);
            $group->post('/leads/{id:[0-9]+}/whatsapp', [LeadsController::class, 'saveWhatsappResult'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}', [LeadsController::class, 'update'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/stage', [LeadsController::class, 'changeStage'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/reassign', [LeadsController::class, 'reassign'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/properties', [LeadsController::class, 'associateProperty'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/property-search', [LeadsController::class, 'savePropertySearch'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/properties/{propertyId:[0-9]+}/delete', [LeadsController::class, 'disassociateProperty'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/delete', [LeadsController::class, 'delete'])->add(new CsrfMiddleware());

            // Interações e Follow-ups de Leads
            $group->post('/leads/{id:[0-9]+}/interactions', [LeadsController::class, 'storeInteraction'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/follow-ups', [LeadsController::class, 'storeFollowUp'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/follow-ups/{followUpId:[0-9]+}/complete', [LeadsController::class, 'completeFollowUp'])->add(new CsrfMiddleware());
            $group->post('/leads/{id:[0-9]+}/follow-ups/{followUpId:[0-9]+}/reschedule', [LeadsController::class, 'rescheduleFollowUp'])->add(new CsrfMiddleware());

            $group->get('/properties', [PropertiesController::class, 'index']);
            $group->get('/properties/new', [PropertiesController::class, 'create']);
            $group->post('/properties', [PropertiesController::class, 'store'])->add(new CsrfMiddleware());
            $group->get('/properties/{id:[0-9]+}', [PropertiesController::class, 'show']);
            $group->get('/properties/{id:[0-9]+}/edit', [PropertiesController::class, 'edit']);
            $group->post('/properties/{id:[0-9]+}', [PropertiesController::class, 'update'])->add(new CsrfMiddleware());
            $group->post('/properties/{id:[0-9]+}/leads', [PropertiesController::class, 'associate'])->add(new CsrfMiddleware());
            $group->post('/properties/{id:[0-9]+}/leads/{leadId:[0-9]+}/delete', [PropertiesController::class, 'disassociate'])->add(new CsrfMiddleware());
            $group->get('/owners', [OwnersController::class, 'index']);
            $group->get('/owners/new', [OwnersController::class, 'create']);
            $group->post('/owners', [OwnersController::class, 'store'])->add(new CsrfMiddleware());
            $group->get('/owners/{id:[0-9]+}', [OwnersController::class, 'show']);
            $group->get('/owners/{id:[0-9]+}/edit', [OwnersController::class, 'edit']);
            $group->post('/owners/{id:[0-9]+}', [OwnersController::class, 'update'])->add(new CsrfMiddleware());
            $group->post('/owners/{id:[0-9]+}/properties', [OwnersController::class, 'associate'])->add(new CsrfMiddleware());
            $group->post('/owners/{id:[0-9]+}/properties/{propertyId:[0-9]+}/delete', [OwnersController::class, 'disassociate'])->add(new CsrfMiddleware());
            $group->get('/owners/{id:[0-9]+}/whatsapp', [OwnersController::class, 'whatsapp']);
            $group->post('/owners/{id:[0-9]+}/whatsapp', [OwnersController::class, 'saveWhatsappResult'])->add(new CsrfMiddleware());
            $group->get('/signboards', [SignboardsController::class, 'index']);
            $group->get('/signboards/new', [SignboardsController::class, 'create']);
            $group->post('/signboards', [SignboardsController::class, 'store'])->add(new CsrfMiddleware());
            $group->get('/signboards/{id:[0-9]+}/assign', [SignboardsController::class, 'assignForm']);
            $group->post('/signboards/{id:[0-9]+}/assign', [SignboardsController::class, 'assign'])->add(new CsrfMiddleware());
            $group->post('/signboards/{id:[0-9]+}/delete', [SignboardsController::class, 'delete'])->add(new CsrfMiddleware());
            $group->get('/signboards/{id:[0-9]+}', [SignboardsController::class, 'show']);
            $group->get('/signboards/{id:[0-9]+}/edit', [SignboardsController::class, 'edit']);
            $group->post('/signboards/{id:[0-9]+}', [SignboardsController::class, 'update'])->add(new CsrfMiddleware());
            $group->post('/signboards/{id:[0-9]+}/install', [SignboardsController::class, 'install'])->add(new CsrfMiddleware());
            $group->post('/signboards/{id:[0-9]+}/remove', [SignboardsController::class, 'remove'])->add(new CsrfMiddleware());
            $group->post('/signboards/{id:[0-9]+}/stage', [SignboardsController::class, 'changeStage'])->add(new CsrfMiddleware());

            // ---------------------------------------------------------
            // Sub-grupo Restrito para Administradores (RBAC)
            // ---------------------------------------------------------
            $group->group('/admin', function (RouteCollectorProxy $adminGroup) {
                $adminGroup->get('/users', [UserController::class, 'index']);
                $adminGroup->post('/users', [UserController::class, 'create'])->add(new CsrfMiddleware());
                $adminGroup->post('/users/{id:[0-9]+}/role', [UserController::class, 'updateRole'])->add(new CsrfMiddleware());
                $adminGroup->post('/users/{id:[0-9]+}/toggle-active', [UserController::class, 'toggleActive'])->add(new CsrfMiddleware());

                $adminGroup->get('/audit', [AuditController::class, 'index']);

                // Painel de Diagnóstico, Monitoramento e Backups
                $adminGroup->get('/system', [SystemController::class, 'index']);
                $adminGroup->post('/system/backup', [SystemController::class, 'triggerBackup'])->add(new CsrfMiddleware());
                $adminGroup->post('/system/test-restore', [SystemController::class, 'triggerTestRestore'])->add(new CsrfMiddleware());
                $adminGroup->post('/system/imports/clear', [SystemController::class, 'clearImportedData'])->add(new CsrfMiddleware());
            })->add(new RoleMiddleware(['admin']));

        })->add(new AuthMiddleware());

        return $app;
    }
}
