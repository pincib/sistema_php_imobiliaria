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

final class SignboardManagementTest extends TestCase
{
    private string $tempDb;
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->tempDb=sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_signboards_'.uniqid().'.sqlite';
        Connection::setPath($this->tempDb);
        (new Migrator())->migrate();
        $this->pdo=Connection::getPdo();
        $hash=password_hash('SenhaSegura123!',PASSWORD_DEFAULT);
        $stmt=$this->pdo->prepare("INSERT INTO users(id,name,email,password_hash,role_id,is_active) VALUES(1,'Gestor','gestor@test.local',:hash,1,1),(2,'Corretora','corretora@test.local',:hash,2,1)");
        $stmt->execute([':hash'=>$hash]);
        $this->app=Application::create();
        SessionManager::start();
        $_SESSION=[];
    }

    protected function tearDown(): void
    {
        SessionManager::destroy();
        Connection::reset();
        if(file_exists($this->tempDb)) @unlink($this->tempDb);
    }

    private function authenticateAs(int $id,string $role): void
    {
        $_SESSION=['user_id'=>$id,'user_name'=>$id===1?'Gestor':'Corretora','user_email'=>'user@test.local','user_role'=>$role,'user_role_label'=>$role==='admin'?'Administrador':'Corretor'];
    }

    private function createSignboard(): int
    {
        $stmt=$this->pdo->prepare("INSERT INTO signboards(property_id,location,responsible_user_id,expires_at,notes,created_by_user_id) VALUES(1,'Grade frontal',2,:expires,'Placa de venda',2)");
        $stmt->execute([':expires'=>date('Y-m-d')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testCorretorCanCreateInstallSearchAndConsultMovementHistory(): void
    {
        $this->authenticateAs(2,'corretor');
        $token=Csrf::getToken();
        $request=(new ServerRequestFactory())->createServerRequest('POST','/signboards')->withParsedBody(['_csrf_token'=>$token,'property_id'=>1,'location'=>'Entrada lateral','responsible_user_id'=>2,'expires_at'=>date('Y-m-d'),'notes'=>'Placa de venda','confirmation_notes'=>'Cadastro confirmado pelo corretor']);
        $response=$this->app->handle($request);
        self::assertSame(302,$response->getStatusCode());
        $id=(int)basename($response->getHeaderLine('Location'));
        self::assertGreaterThan(0,$id);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM signboards WHERE id={$id}")->fetchColumn());

        $install=(new ServerRequestFactory())->createServerRequest('POST',"/signboards/{$id}/install")->withParsedBody(['_csrf_token'=>$token,'date'=>date('Y-m-d'),'notes'=>'Instalação confirmada no local']);
        self::assertSame(302,$this->app->handle($install)->getStatusCode());
        $this->pdo->prepare("UPDATE signboards SET expires_at='2000-01-01' WHERE id=:id")->execute([':id'=>$id]);
        $detail=(new ServerRequestFactory())->createServerRequest('GET',"/signboards/{$id}");
        $body=(string)$this->app->handle($detail)->getBody();
        self::assertStringContainsString('Vencida',$body);
        self::assertStringContainsString('Instalação confirmada no local',$body);
        self::assertStringContainsString('Corretora',$body);
        $search=(new ServerRequestFactory())->createServerRequest('GET','/signboards?status=vencida&q=Entrada%20lateral');
        $searchBody=(string)$this->app->handle($search)->getBody();
        self::assertStringContainsString('Entrada lateral',$searchBody);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='signboard.install' AND entity_id='{$id}'")->fetchColumn());
    }

    public function testRetirementIsConfirmedAndPreservesHistory(): void
    {
        $this->authenticateAs(2,'corretor');
        $id=$this->createSignboard();
        $this->pdo->prepare('UPDATE signboards SET installed_at=:date WHERE id=:id')->execute([':date'=>date('Y-m-d'),':id'=>$id]);
        $token=Csrf::getToken();
        $request=(new ServerRequestFactory())->createServerRequest('POST',"/signboards/{$id}/remove")->withParsedBody(['_csrf_token'=>$token,'date'=>date('Y-m-d'),'notes'=>'Retirada confirmada e placa guardada']);
        self::assertSame(302,$this->app->handle($request)->getStatusCode());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM signboards WHERE id={$id} AND removed_at IS NOT NULL")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM signboard_movements WHERE signboard_id={$id} AND movement_type='removed'")->fetchColumn());
    }

    public function testAdminAuditRouteRemainsRestrictedFromBroker(): void
    {
        $this->authenticateAs(2,'corretor');
        $response=$this->app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin/audit'));
        self::assertSame(403,$response->getStatusCode());
        $this->authenticateAs(1,'admin');
        self::assertSame(200,$this->app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin/audit'))->getStatusCode());
    }

    public function testPendingSignboardCannotBeRemovedAndReturnsRecoverableValidationError(): void
    {
        $this->authenticateAs(2,'corretor');
        $id=$this->createSignboard();
        $token=Csrf::getToken();
        $request=(new ServerRequestFactory())->createServerRequest('POST',"/signboards/{$id}/remove")
            ->withParsedBody(['_csrf_token'=>$token,'date'=>date('Y-m-d'),'notes'=>'Tentativa de retirada de placa pendente']);

        $response=$this->app->handle($request);

        self::assertSame(422,$response->getStatusCode());
        self::assertStringContainsString('Instale a placa antes de registrar a retirada.',(string)$response->getBody());
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM signboards WHERE id={$id} AND removed_at IS NOT NULL")->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM signboard_movements WHERE signboard_id={$id} AND movement_type='removed'")->fetchColumn());
    }

    public function testRepeatedInstallationIsRejectedWithoutDuplicatingHistory(): void
    {
        $this->authenticateAs(2,'corretor');
        $id=$this->createSignboard();
        $service=new \App\Modules\Signboards\Services\SignboardService();

        self::assertTrue($service->install($id,date('Y-m-d'),'Instalação confirmada',2)['success']);
        $retry=$service->install($id,date('Y-m-d'),'Repetição da confirmação',2);

        self::assertFalse($retry['success']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM signboard_movements WHERE signboard_id={$id} AND movement_type='installed'")->fetchColumn());
    }

    public function testSearchFindsSignboardByPropertyAddressAndCapsLongQuery(): void
    {
        $this->authenticateAs(2,'corretor');
        $id=$this->createSignboard();
        $this->pdo->exec("UPDATE properties SET address='Rua das Palmeiras, 123' WHERE id=1");

        $response=$this->app->handle((new ServerRequestFactory())->createServerRequest('GET','/signboards?q=Palmeiras'));
        $body=(string)$response->getBody();
        self::assertSame(200,$response->getStatusCode());
        self::assertStringContainsString("/signboards/{$id}",$body);
        self::assertStringContainsString('Grade frontal',$body);

        $longQuery=str_repeat('x',500);
        $longResponse=$this->app->handle((new ServerRequestFactory())->createServerRequest('GET','/signboards?q='.$longQuery));
        self::assertSame(200,$longResponse->getStatusCode());
        self::assertStringContainsString('maxlength="120"',(string)$longResponse->getBody());
    }

    public function testValidationErrorsKeepInputAndAreAssociatedWithFields(): void
    {
        $this->authenticateAs(2,'corretor');
        $token=Csrf::getToken();
        $create=(new ServerRequestFactory())->createServerRequest('POST','/signboards')->withParsedBody([
            '_csrf_token'=>$token,
            'property_id'=>1,
            'location'=>'',
            'responsible_user_id'=>2,
            'expires_at'=>'',
            'notes'=>'Observação que deve permanecer',
            'confirmation_notes'=>'Cadastro combinado com o proprietário',
        ]);
        $createResponse=$this->app->handle($create);
        $createBody=(string)$createResponse->getBody();
        self::assertSame(422,$createResponse->getStatusCode());
        self::assertStringContainsString('aria-describedby="signboard-location-error"',$createBody);
        self::assertStringContainsString('id="signboard-location-error"',$createBody);
        self::assertStringContainsString('Observação que deve permanecer',$createBody);
        self::assertStringContainsString('Cadastro combinado com o proprietário',$createBody);

        $id=$this->createSignboard();
        $install=(new ServerRequestFactory())->createServerRequest('POST',"/signboards/{$id}/install")->withParsedBody([
            '_csrf_token'=>$token,'date'=>'2026-02-30','notes'=>'Confirmação digitada antes do erro',
        ]);
        $installResponse=$this->app->handle($install);
        $installBody=(string)$installResponse->getBody();
        self::assertSame(422,$installResponse->getStatusCode());
        self::assertStringContainsString('Informe uma data de instalação válida, não futura.',$installBody);
        self::assertStringContainsString('Confirmação digitada antes do erro',$installBody);
        self::assertNull($this->pdo->query("SELECT installed_at FROM signboards WHERE id={$id}")->fetchColumn());
    }
}
