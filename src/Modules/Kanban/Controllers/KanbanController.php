<?php
declare(strict_types=1);
namespace App\Modules\Kanban\Controllers;

use App\Audit\AuditService;
use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Leads\Services\LeadService;
use App\Modules\Properties\Services\PropertyService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class KanbanController
{
    private const OWNER_STAGES = ['a_contatar'=>'A contatar', 'contatado'=>'Contatado', 'mantido'=>'Mantido', 'fora_do_ar'=>'Fora do ar'];
    private function config(string $type): array
    {
        return match ($type) {
            'leads' => ['table'=>'leads','field'=>'stage','columns'=>PipelineManager::STAGES,'title'=>'Leads'],
            'properties' => ['table'=>'properties','field'=>'status','columns'=>PropertyService::STATUSES,'title'=>'Imóveis'],
            'owners' => ['table'=>'owners','field'=>'contact_stage','columns'=>self::OWNER_STAGES,'title'=>'Proprietários'],
            default => [],
        };
    }
    public function board(Request $request, Response $response, array $args): Response
    {
        $type=$args['type']; $config=$this->config($type);
        if (!$config) throw new HttpNotFoundException($request);
        $q=trim((string)($request->getQueryParams()['q']??''));
        $search=$type==='properties' ? '(code LIKE :q OR title LIKE :q OR city LIKE :q OR neighborhood LIKE :q)' : '(name LIKE :q OR phone LIKE :q OR email LIKE :q)';
        $stmt=Connection::getPdo()->prepare("SELECT * FROM {$config['table']} WHERE {$search} ORDER BY updated_at DESC, id DESC");
        $stmt->execute([':q'=>'%'.$q.'%']); $items=$stmt->fetchAll(\PDO::FETCH_ASSOC);
        $response->getBody()->write((new Renderer())->render('kanban/board',compact('type','config','items','q')+['title'=>'Quadro — '.$config['title']]));
        return $response;
    }
    public function move(Request $request, Response $response, array $args): Response
    {
        $type=$args['type']; $config=$this->config($type);
        if (!$config) throw new HttpNotFoundException($request);
        $id=(int)$args['id']; $body=(array)$request->getParsedBody(); $target=(string)($body['stage']??'');
        $pdo=Connection::getPdo(); $s=$pdo->prepare("SELECT * FROM {$config['table']} WHERE id=:id"); $s->execute([':id'=>$id]); $item=$s->fetch(\PDO::FETCH_ASSOC);
        if (!$item) throw new HttpNotFoundException($request);
        $actor=(int)(AuthService::user()['id']??0); $old=$item[$config['field']];
        if (!isset($config['columns'][$target])) SessionManager::flash('error','Selecione uma coluna válida.');
        elseif ($old===$target) SessionManager::flash('info','O cartão já está nessa coluna.');
        elseif ($type==='leads') {
            $result=(new LeadService())->changeStage($id,$target,trim((string)($body['loss_reason']??'')),$actor);
            SessionManager::flash($result['success']?'success':'error',$result['success']?'Estágio atualizado.':$result['error']);
        } else {
            Connection::transaction(function(\PDO $pdo) use ($config,$target,$id,$type,$old,$actor): void {
                $s=$pdo->prepare("UPDATE {$config['table']} SET {$config['field']}=:target, import_batch_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
                $s->execute([':target'=>$target,':id'=>$id]);
                AuditService::log(($type==='owners'?'owner.contact_stage_change':'property.status_change'),$type==='owners'?'owner':'property',(string)$id,[$config['field']=>$old],[$config['field']=>$target],$actor);
            });
            SessionManager::flash('success','Cartão movido para '.$config['columns'][$target].'.');
        }
        return $response->withHeader('Location','/kanban/'.$type)->withStatus(302);
    }
    public function label(Request $request, Response $response, array $args): Response
    {
        $type=$args['type']; if (!in_array($type,['leads','owners'],true)) throw new HttpNotFoundException($request);
        $id=(int)$args['id']; $body=(array)$request->getParsedBody(); $value=(string)($body['invalid_contact']??'');
        if (!in_array($value,['0','1'],true)) return $response->withStatus(400);
        $pdo=Connection::getPdo(); $s=$pdo->prepare("SELECT invalid_contact FROM $type WHERE id=:id"); $s->execute([':id'=>$id]); $old=$s->fetchColumn();
        if ($old===false) throw new HttpNotFoundException($request);
        if ((int)$old!==(int)$value) Connection::transaction(function(\PDO $pdo) use ($type,$id,$value,$old): void {
            $s=$pdo->prepare("UPDATE $type SET invalid_contact=:value, import_batch_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=:id"); $s->execute([':value'=>(int)$value,':id'=>$id]);
            AuditService::log(($type==='leads'?'lead':'owner').'.invalid_contact_change',$type==='leads'?'lead':'owner',(string)$id,['invalid_contact'=>(int)$old],['invalid_contact'=>(int)$value],(int)(AuthService::user()['id']??0));
        });
        SessionManager::flash('success', $value==='1'?'Contato marcado como inválido.':'Rótulo de contato inválido removido.');
        $back=(string)($body['return_to']??'');
        $allowed=['/contacts/invalid','/kanban/'.$type,'/'.$type.'/'.$id];
        return $response->withHeader('Location',in_array($back,$allowed,true)?$back:'/'.$type.'/'.$id)->withStatus(302);
    }
    public function invalid(Request $request, Response $response): Response
    {
        $pdo=Connection::getPdo(); $leads=$pdo->query('SELECT id,name,phone,email FROM leads WHERE invalid_contact=1 ORDER BY name COLLATE NOCASE')->fetchAll(\PDO::FETCH_ASSOC);
        $owners=$pdo->query('SELECT id,name,phone,email FROM owners WHERE invalid_contact=1 ORDER BY name COLLATE NOCASE')->fetchAll(\PDO::FETCH_ASSOC);
        $response->getBody()->write((new Renderer())->render('kanban/invalid',compact('leads','owners')+['title'=>'Contatos inválidos'])); return $response;
    }
}
