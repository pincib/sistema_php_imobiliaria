<?php

declare(strict_types=1);

namespace App\Modules\Signboards\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Modules\Signboards\Services\SignboardService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SignboardsController
{
    private SignboardService $service;
    private Renderer $renderer;

    public function __construct(?SignboardService $service = null, ?Renderer $renderer = null)
    {
        $this->service = $service ?? new SignboardService();
        $this->renderer = $renderer ?? new Renderer();
    }

    public function index(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $query = $q['q'] ?? '';
        $status = $q['status'] ?? '';
        $filters = [
            'q'=>mb_substr(is_string($query) ? trim($query) : '', 0, 120),
            'status'=>is_string($status) && isset(SignboardService::STATUSES[$status]) ? $status : '',
        ];
        $result = $this->service->repository()->paginate(max(1,(int)($q['page'] ?? 1)), 20, $filters);
        return $this->render($response, 'signboards/index', ['title'=>'Placas', 'overview'=>$this->service->getOverview(), 'signboards'=>$result['data'], 'total'=>$result['total'], 'page'=>$result['page'], 'totalPages'=>$result['total_pages'], 'filters'=>$filters, 'statuses'=>SignboardService::STATUSES]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'signboards/form', ['title'=>'Cadastrar placa','signboard'=>[],'errors'=>[],'properties'=>$this->service->repository()->properties(),'users'=>$this->service->repository()->activeUsers(),'action'=>'/signboards','editing'=>false]);
    }

    public function store(Request $request, Response $response): Response
    {
        return $this->save($request,$response);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id=(int)$args['id']; $row=$this->service->repository()->find($id);
        if (!$row || $row['removed_at'] !== null) return $response->withHeader('Location','/signboards')->withStatus(302);
        return $this->render($response,'signboards/form',['title'=>'Editar placa','signboard'=>$row,'errors'=>[],'properties'=>$this->service->repository()->properties(),'users'=>$this->service->repository()->activeUsers(),'action'=>'/signboards/'.$id,'editing'=>true]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return $this->save($request,$response,(int)$args['id']);
    }

    private function save(Request $request, Response $response, ?int $id=null): Response
    {
        $input=(array)($request->getParsedBody() ?? []);
        $result=$this->service->save($input,(int)(AuthService::user()['id'] ?? 0),$id);
        if (!$result['success']) return $this->render($response,'signboards/form',['title'=>$id?'Editar placa':'Cadastrar placa','signboard'=>$input,'errors'=>$result['errors'],'properties'=>$this->service->repository()->properties(),'users'=>$this->service->repository()->activeUsers(),'action'=>$id?'/signboards/'.$id:'/signboards','editing'=>$id!==null])->withStatus(422);
        SessionManager::flash('success',$id?'Cadastro da placa atualizado.':'Placa cadastrada e histórico iniciado.');
        return $response->withHeader('Location','/signboards/'.$result['signboard_id'])->withStatus(302);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return $this->renderDetail($response,(int)$args['id']);
    }

    public function install(Request $request, Response $response, array $args): Response
    {
        $body=(array)($request->getParsedBody() ?? []);
        $id=(int)$args['id'];
        $result=$this->service->install($id,$this->inputText($body,'date'),$this->inputText($body,'notes'),(int)(AuthService::user()['id'] ?? 0));
        if (!$result['success']) return $this->renderDetail($response,$id,422,$result['error'],$body,'install');
        SessionManager::flash('success','Instalação confirmada e registrada no histórico.');
        return $response->withHeader('Location','/signboards/'.$id)->withStatus(302);
    }

    public function remove(Request $request, Response $response, array $args): Response
    {
        $body=(array)($request->getParsedBody() ?? []);
        $id=(int)$args['id'];
        $result=$this->service->remove($id,$this->inputText($body,'date'),$this->inputText($body,'notes'),(int)(AuthService::user()['id'] ?? 0));
        if (!$result['success']) return $this->renderDetail($response,$id,422,$result['error'],$body,'remove');
        SessionManager::flash('success','Retirada confirmada. A placa continua disponível no histórico.');
        return $response->withHeader('Location','/signboards/'.$id)->withStatus(302);
    }

    private function renderDetail(Response $response, int $id, int $status=200, ?string $actionError=null, array $actionInput=[], ?string $actionKind=null): Response
    {
        $row=$this->service->repository()->find($id);
        if (!$row) return $response->withHeader('Location','/signboards')->withStatus(302);
        return $this->render($response,'signboards/show',['title'=>'Placa · '.$row['property_code'],'signboard'=>$row,'movements'=>$this->service->repository()->movements($id),'statuses'=>SignboardService::STATUSES,'actionError'=>$actionError,'actionInput'=>$actionInput,'actionKind'=>$actionKind])->withStatus($status);
    }

    private function inputText(array $input,string $key): string
    {
        $value=$input[$key] ?? '';
        return is_string($value) || is_numeric($value) ? trim((string)$value) : '';
    }

    private function render(Response $response,string $template,array $data): Response
    {
        $response->getBody()->write($this->renderer->render($template,$data));
        return $response;
    }
}
