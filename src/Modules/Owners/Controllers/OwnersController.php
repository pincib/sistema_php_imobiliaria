<?php
declare(strict_types=1);
namespace App\Modules\Owners\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Database\Connection;
use App\Modules\Leads\Support\LeadNormalizer;
use App\Modules\Properties\Repositories\PropertyRepository;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class OwnersController
{
    private Renderer $view;
    public function __construct(?Renderer $view=null) { $this->view=$view??new Renderer(); }
    private function pdo(): \PDO { return Connection::getPdo(); }
    private function page(Response $r,string $template,array $data): Response { $r->getBody()->write($this->view->render($template,$data)); return $r; }

    public function index(Request $request,Response $response): Response
    {
        $q=trim((string)($request->getQueryParams()['q']??'')); $page=max(1,(int)($request->getQueryParams()['page']??1)); $size=20; $like='%'.$q.'%';
        $s=$this->pdo()->prepare("SELECT COUNT(DISTINCT o.id) FROM owners o LEFT JOIN owner_properties op ON op.owner_id=o.id LEFT JOIN properties p ON p.id=op.property_id WHERE :q='' OR o.name LIKE :name OR o.email LIKE :email OR o.phone LIKE :phone OR p.code LIKE :code"); $s->execute([':q'=>$q,':name'=>$like,':email'=>$like,':phone'=>'%'.preg_replace('/\D/','',$q).'%',':code'=>$like]); $total=(int)$s->fetchColumn();
        $pages=max(1,(int)ceil($total/$size)); $page=min($page,$pages); $s=$this->pdo()->prepare("SELECT o.*,COUNT(DISTINCT op.property_id) property_count,GROUP_CONCAT(DISTINCT p.code) property_codes FROM owners o LEFT JOIN owner_properties op ON op.owner_id=o.id LEFT JOIN properties p ON p.id=op.property_id WHERE :q='' OR o.name LIKE :name OR o.email LIKE :email OR o.phone LIKE :phone OR p.code LIKE :code GROUP BY o.id ORDER BY o.name COLLATE NOCASE LIMIT :limit OFFSET :offset");
        foreach([':q'=>$q,':name'=>$like,':email'=>$like,':phone'=>'%'.preg_replace('/\D/','',$q).'%',':code'=>$like] as $k=>$v)$s->bindValue($k,$v); $s->bindValue(':limit',$size,\PDO::PARAM_INT);$s->bindValue(':offset',($page-1)*$size,\PDO::PARAM_INT);$s->execute();
        return $this->page($response,'owners/index',['title'=>'Proprietários','owners'=>$s->fetchAll(\PDO::FETCH_ASSOC),'q'=>$q,'total'=>$total,'page'=>$page,'pages'=>$pages,'formatPhone'=>[LeadNormalizer::class,'formatPhone']]);
    }
    public function create(Request $q,Response $r): Response { return $this->form($r,[],[], '/owners','Novo proprietário'); }
    public function edit(Request $q,Response $r,array $a): Response { $o=$this->find((int)$a['id']); return $o?$this->form($r,$o,[],'/owners/'.$o['id'],'Editar proprietário'):$r->withHeader('Location','/owners')->withStatus(302); }
    private function form(Response $r,array $owner,array $errors,string $action,string $title): Response { $s=$this->pdo()->query('SELECT id,code,title FROM properties ORDER BY code'); return $this->page($r,'owners/form',['title'=>$title,'owner'=>$owner,'errors'=>$errors,'action'=>$action,'properties'=>$s->fetchAll(\PDO::FETCH_ASSOC)]); }
    public function store(Request $q,Response $r): Response { return $this->save($q,$r); }
    public function update(Request $q,Response $r,array $a): Response { return $this->save($q,$r,(int)$a['id']); }
    private function save(Request $request,Response $response,?int $id=null): Response
    {
        $isCreate=$id===null;$b=(array)($request->getParsedBody()??[]);$name=LeadNormalizer::sanitizeName((string)($b['name']??''));$email=LeadNormalizer::normalizeEmail((string)($b['email']??''));$phone=LeadNormalizer::normalizePhone((string)($b['phone']??''));$notes=trim((string)($b['notes']??''));$propertySelectionProvided=array_key_exists('property_ids',$b);$propertyIds=array_values(array_unique(array_filter(array_map('intval',(array)($b['property_ids']??[])),static fn($i)=>$i>0)));$errors=[];
        if(!mb_check_encoding($name,'UTF-8')||mb_strlen($name)<2||mb_strlen($name)>150)$errors['name']='Informe um nome válido entre 2 e 150 caracteres.';
        if($email!==null&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>150))$errors['email']='Informe um e-mail válido de até 150 caracteres.';
        if($phone!==null&&(strlen($phone)<10||strlen($phone)>11))$errors['phone']='O telefone deve conter DDD e 10 ou 11 dígitos.';
        if(!mb_check_encoding($notes,'UTF-8')||mb_strlen($notes)>4000||preg_match('/[\x00-\x08\x0B\x0C\x0E\x0F\x10-\x1F\x7F]/u',$notes))$errors['notes']='As observações devem ter até 4.000 caracteres e não conter caracteres de controle.';
        $pdo=$this->pdo();$st=$pdo->prepare('SELECT id,code FROM properties WHERE id IN ('.implode(',',array_fill(0,count($propertyIds),'?')).')');if($propertyIds)$st->execute($propertyIds);$valid=$propertyIds?$st->fetchAll(\PDO::FETCH_COLUMN):[];if(count($valid)!==count($propertyIds))$errors['property_ids']='Um ou mais imóveis selecionados não existem.';
        if(!$errors){$dupSql='SELECT id,name FROM owners WHERE '.($email?'email=:email COLLATE NOCASE':'0').($phone?' OR phone=:phone':'').(!$email&&!$phone?' OR name=:name COLLATE NOCASE':'');$dupParams=[];if($email)$dupParams[':email']=$email;if($phone)$dupParams[':phone']=$phone;if(!$email&&!$phone)$dupParams[':name']=$name;if($id){$dupSql='SELECT id,name FROM owners WHERE id<>:id AND ('.substr($dupSql,strpos($dupSql,'WHERE ')+6).')';$dupParams[':id']=$id;}$d=$pdo->prepare($dupSql.' LIMIT 1');$d->execute($dupParams);$dup=$d->fetch(\PDO::FETCH_ASSOC);if($dup)$errors['general']='Já existe um proprietário com estes dados ('.$dup['name'].' — #'.$dup['id'].').';}
        if($errors)return $this->form($response,$b+['property_ids'=>$propertyIds],$errors,$id?'/owners/'.$id:'/owners',$id?'Editar proprietário':'Novo proprietário')->withStatus(422);
        $actor=(int)(AuthService::user()['id']??0);$oldValues=null;
        $saved=\App\Database\Connection::transaction(function(\PDO $pdo) use (&$id,$isCreate,$propertySelectionProvided,$name,$email,$phone,$notes,$propertyIds,$actor,&$oldValues): bool {
            if($id){$old=$this->find($id);if(!$old)return false;$oldLinks=$pdo->prepare('SELECT property_id FROM owner_properties WHERE owner_id=:id ORDER BY property_id');$oldLinks->execute([':id'=>$id]);$oldValues=['name'=>$old['name'],'email'=>$old['email'],'phone'=>$old['phone'],'notes'=>$old['notes'],'property_ids'=>array_map('intval',$oldLinks->fetchAll(\PDO::FETCH_COLUMN))];$st=$pdo->prepare('UPDATE owners SET name=:name,email=:email,phone=:phone,notes=:notes,import_batch_id=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$st->execute([':name'=>$name,':email'=>$email,':phone'=>$phone,':notes'=>$notes?:null,':id'=>$id]);}
            else{$st=$pdo->prepare('INSERT INTO owners(name,email,phone,notes) VALUES(:name,:email,:phone,:notes)');$st->execute([':name'=>$name,':email'=>$email,':phone'=>$phone,':notes'=>$notes?:null]);$id=(int)$pdo->lastInsertId();}
            if($propertySelectionProvided){
                $link=$pdo->prepare('INSERT INTO owner_properties(owner_id,property_id,linked_by_user_id) VALUES(:owner,:property,:user) ON CONFLICT(owner_id,property_id) DO NOTHING');foreach($propertyIds as $pid)$link->execute([':owner'=>$id,':property'=>$pid,':user'=>$actor]);
                $pdo->prepare('UPDATE owner_properties SET import_batch_id=NULL WHERE owner_id=:owner')->execute([':owner'=>$id]);
                if($propertyIds){$delete=$pdo->prepare('DELETE FROM owner_properties WHERE owner_id=:owner AND property_id NOT IN ('.implode(',',array_fill(0,count($propertyIds),'?')).')');$delete->bindValue(':owner',$id,\PDO::PARAM_INT);foreach($propertyIds as $index=>$pid)$delete->bindValue($index+1,$pid,\PDO::PARAM_INT);$delete->execute();}
                else {$delete=$pdo->prepare('DELETE FROM owner_properties WHERE owner_id=:owner');$delete->execute([':owner'=>$id]);}
            }
            \App\Audit\AuditService::log($isCreate?'owner.create':'owner.update','owner',(string)$id,$oldValues,['name'=>$name,'email'=>$email,'phone'=>$phone,'notes'=>$notes?:null,'property_ids'=>$propertyIds],$actor);
            return true;
        });
        if(!$saved)return $this->form($response,$b,['general'=>'Proprietário não encontrado.'],'/owners','Editar proprietário')->withStatus(404);
        SessionManager::flash('success',$isCreate?'Proprietário cadastrado.':'Proprietário atualizado.');return $response->withHeader('Location','/owners/'.$id)->withStatus(302);
    }
    private function find(int $id): ?array { $s=$this->pdo()->prepare('SELECT * FROM owners WHERE id=:id');$s->execute([':id'=>$id]);return $s->fetch(\PDO::FETCH_ASSOC)?:null; }
    public function show(Request $request,Response $response,array $args): Response
    {
        $id=(int)$args['id'];$owner=$this->find($id);if(!$owner)throw new HttpNotFoundException($request,'Proprietário não encontrado.');
        $s=$this->pdo()->prepare('SELECT p.*,op.created_at linked_at FROM owner_properties op JOIN properties p ON p.id=op.property_id WHERE op.owner_id=:id ORDER BY p.code');$s->execute([':id'=>$id]);$properties=$s->fetchAll(\PDO::FETCH_ASSOC);
        $s=$this->pdo()->prepare('SELECT p.id,p.code,p.title FROM properties p WHERE p.id NOT IN(SELECT property_id FROM owner_properties WHERE owner_id=:id) ORDER BY p.code');$s->execute([':id'=>$id]);$available=$s->fetchAll(\PDO::FETCH_ASSOC);
        return $this->page($response,'owners/show',['title'=>$owner['name'].' — Proprietário','owner'=>$owner,'properties'=>$properties,'available'=>$available,'formatPhone'=>[LeadNormalizer::class,'formatPhone']]);
    }
    public function associate(Request $request,Response $response,array $args): Response
    {
        $id=(int)$args['id'];if(!$this->find($id))throw new HttpNotFoundException($request,'Proprietário não encontrado.');$body=(array)($request->getParsedBody()??[]);$code=trim((string)($body['property_code']??''));$p=(new PropertyRepository())->findByCode($code);if(!$p)SessionManager::flash('error','Código de imóvel não encontrado.');else{$s=$this->pdo()->prepare('INSERT INTO owner_properties(owner_id,property_id,linked_by_user_id) VALUES(:o,:p,:u) ON CONFLICT(owner_id,property_id) DO NOTHING');$s->execute([':o'=>$id,':p'=>$p['id'],':u'=>(int)(AuthService::user()['id']??0)]);if($s->rowCount())\App\Audit\AuditService::log('owner.property_associated','owner',(string)$id,null,['property_id'=>(int)$p['id'],'property_code'=>$p['code']],(int)(AuthService::user()['id']??0));SessionManager::flash('success','Imóvel '.$p['code'].' vinculado ao proprietário.');}return $response->withHeader('Location','/owners/'.$id)->withStatus(302);
    }
    public function disassociate(Request $request,Response $response,array $args): Response { $ownerId=(int)$args['id'];if(!$this->find($ownerId))throw new HttpNotFoundException($request,'Proprietário não encontrado.');$propertyId=(int)$args['propertyId'];$s=$this->pdo()->prepare('SELECT code FROM properties WHERE id=:id');$s->execute([':id'=>$propertyId]);$code=$s->fetchColumn();$s=$this->pdo()->prepare('DELETE FROM owner_properties WHERE owner_id=:o AND property_id=:p');$s->execute([':o'=>$ownerId,':p'=>$propertyId]);if($s->rowCount())\App\Audit\AuditService::log('owner.property_disassociated','owner',(string)$ownerId,['property_id'=>$propertyId,'property_code'=>$code],null,(int)(AuthService::user()['id']??0));SessionManager::flash('success','Vínculo do imóvel removido.');return $response->withHeader('Location','/owners/'.$ownerId)->withStatus(302); }
    public function whatsapp(Request $request,Response $response,array $args): Response { return $this->renderWhatsapp($request,$response,(int)$args['id']); }
    private function renderWhatsapp(Request $request,Response $response,int $id): Response
    {
        $owner=$this->find($id);if(!$owner)throw new HttpNotFoundException($request,'Proprietário não encontrado.');$s=$this->pdo()->prepare('SELECT p.id AS property_id,p.code,p.title FROM owner_properties op JOIN properties p ON p.id=op.property_id WHERE op.owner_id=:id ORDER BY p.code');$s->execute([':id'=>$id]);$props=$s->fetchAll(\PDO::FETCH_ASSOC);$h=$this->pdo()->prepare("SELECT w.*,u.name user_name FROM whatsapp_contact_logs w LEFT JOIN users u ON u.id=w.user_id WHERE contact_type='owner' AND contact_id=:id ORDER BY w.id DESC LIMIT 20");$h->execute([':id'=>$id]);
        $rules = match (true) {
            count($props) === 1 => ['Atualização mensal — 1 imóvel'],
            count($props) >= 2 => ['Atualização mensal — 2 ou mais imóveis'],
            default => [],
        };
        return $this->page($response,'contacts/whatsapp',['title'=>'WhatsApp — '.$owner['name'],'contactType'=>'owner','contact'=>$owner,'properties'=>$props,'history'=>$h->fetchAll(\PDO::FETCH_ASSOC),'action'=>'/owners/'.$id.'/whatsapp','returnUrl'=>'/owners/'.$id,'rules'=>$rules]);
    }
    public function saveWhatsappResult(Request $request,Response $response,array $args): Response
    {
        $id=(int)$args['id'];
        if(!$this->find($id))throw new HttpNotFoundException($request,'Proprietário não encontrado.');
        $b=(array)($request->getParsedBody()??[]);
        $propertyCountStatement=$this->pdo()->prepare('SELECT COUNT(*) FROM owner_properties WHERE owner_id=:id');
        $propertyCountStatement->execute([':id'=>$id]);
        $propertyCount=(int)$propertyCountStatement->fetchColumn();
        $allowed=match(true) {
            $propertyCount===1 => ['Atualização mensal — 1 imóvel'],
            $propertyCount>=2 => ['Atualização mensal — 2 ou mais imóveis'],
            default => [],
        };
        $results=['confirmed'=>'confirmado pelo corretor','not_sent'=>'não enviado','cancelled'=>'cancelado'];
        $rule=(string)($b['rule']??'');$message=trim((string)($b['message']??''));$result=(string)($b['result']??'');
        $validEncoding=mb_check_encoding($message,'UTF-8');
        $hasControlCharacters=$validEncoding && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$message);
        if(!in_array($rule,$allowed,true)||$message===''||!$validEncoding||mb_strlen($message)>4000||$hasControlCharacters||!isset($results[$result])) {
            SessionManager::flash('error','Não foi possível registrar: revise régua, mensagem válida (até 4.000 caracteres) e resultado.');
        } else {
            $s=$this->pdo()->prepare('INSERT INTO whatsapp_contact_logs(contact_type,contact_id,rule,message,result,user_id) VALUES(\'owner\',:id,:rule,:message,:result,:user)');
            $s->execute([':id'=>$id,':rule'=>$rule,':message'=>$message,':result'=>$results[$result],':user'=>(int)(AuthService::user()['id']??0)]);
            SessionManager::flash('success','Resultado registrado no histórico.');
        }
        return $response->withHeader('Location','/owners/'.$id.'/whatsapp')->withStatus(302);
    }
}
