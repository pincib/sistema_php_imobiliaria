<?php
declare(strict_types=1);
namespace App\Tests\Feature;
use App\Application;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Database\Migrator;
use App\Security\Csrf;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class KanbanWorkflowTest extends TestCase
{
    private string $db;
    private $app;
    private \PDO $pdo;
    protected function setUp(): void {
        $this->db=sys_get_temp_dir().'/kanban_'.uniqid().'.sqlite'; Connection::setPath($this->db); (new Migrator())->migrate(); $this->pdo=Connection::getPdo();
        $hash=password_hash('senha',PASSWORD_DEFAULT); $s=$this->pdo->prepare("INSERT INTO users(id,name,email,password_hash,role_id,is_active) VALUES(1,'Corretor','kanban@example.com',:hash,2,1)"); $s->execute([':hash'=>$hash]);
        $this->pdo->exec("INSERT INTO leads(id,name) VALUES(1,'Lead teste'); INSERT INTO owners(id,name) VALUES(1,'Proprietário teste'); INSERT INTO owner_properties(owner_id,property_id) VALUES(1,1)");
        $this->app=Application::create(); SessionManager::start(); $_SESSION=['user_id'=>1,'user_name'=>'Corretor','user_email'=>'kanban@example.com','user_role'=>'corretor','user_role_label'=>'Corretor'];
    }
    protected function tearDown(): void { SessionManager::destroy(); Connection::reset(); @unlink($this->db); }
    private function post(string $url,array $data): \Psr\Http\Message\ResponseInterface { return $this->app->handle((new ServerRequestFactory())->createServerRequest('POST',$url)->withParsedBody(['_csrf_token'=>Csrf::getToken()]+$data)); }
    public function testBoardsMoveUsingDomainRulesAndLabels(): void {
        foreach(['leads','owners','properties'] as $type) { $r=$this->app->handle((new ServerRequestFactory())->createServerRequest('GET','/kanban/'.$type)); self::assertSame(200,$r->getStatusCode()); self::assertStringContainsString('kanban-column',(string)$r->getBody()); }
        $this->post('/kanban/leads/1',['stage'=>'ganho']); self::assertSame('novo',$this->pdo->query('SELECT stage FROM leads WHERE id=1')->fetchColumn());
        $this->post('/kanban/leads/1',['stage'=>'perdido']); self::assertSame('novo',$this->pdo->query('SELECT stage FROM leads WHERE id=1')->fetchColumn());
        $this->post('/kanban/leads/1',['stage'=>'perdido','loss_reason'=>'Sem interesse']); self::assertSame('perdido',$this->pdo->query('SELECT stage FROM leads WHERE id=1')->fetchColumn());
        $this->post('/kanban/properties/1',['stage'=>'reservado']); self::assertSame('reservado',$this->pdo->query('SELECT status FROM properties WHERE id=1')->fetchColumn());
        $this->post('/contacts/leads/1/invalid',['invalid_contact'=>'1']); $this->post('/contacts/owners/1/invalid',['invalid_contact'=>'1']);
        $r=$this->app->handle((new ServerRequestFactory())->createServerRequest('GET','/contacts/invalid')); self::assertStringContainsString('Lead teste',(string)$r->getBody()); self::assertStringContainsString('Proprietário teste',(string)$r->getBody());
        $this->post('/contacts/owners/1/invalid',['invalid_contact'=>'0']); self::assertSame(0,(int)$this->pdo->query('SELECT invalid_contact FROM owners WHERE id=1')->fetchColumn());
    }
    public function testOnlyConfirmedWhatsappMovesOwnerAndIsAudited(): void {
        $this->post('/kanban/owners/1',['stage'=>'mantido']);
        $data=['rule'=>'Atualização mensal — 1 imóvel','message'=>'Olá','result'=>'not_sent']; $this->post('/owners/1/whatsapp',$data); self::assertSame('mantido',$this->pdo->query('SELECT contact_stage FROM owners WHERE id=1')->fetchColumn());
        $data['result']='confirmed'; $this->post('/owners/1/whatsapp',$data); self::assertSame('contatado',$this->pdo->query('SELECT contact_stage FROM owners WHERE id=1')->fetchColumn());
        self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='owner.contact_stage_change'")->fetchColumn());
    }
}
