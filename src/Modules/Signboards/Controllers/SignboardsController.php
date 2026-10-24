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
        $signboards = $this->service->repository()->board($filters);
        $stageCounts = array_fill_keys(array_keys(SignboardService::BOARD_STAGES), 0);
        foreach ($signboards as $signboard) $stageCounts[$signboard['board_stage']] = ($stageCounts[$signboard['board_stage']] ?? 0) + 1;
        return $this->render($response, 'signboards/index', ['title'=>'Placas', 'overview'=>$this->service->getOverview(), 'signboards'=>$signboards, 'stageCounts'=>$stageCounts, 'filters'=>$filters, 'statuses'=>SignboardService::STATUSES, 'stages'=>SignboardService::BOARD_STAGES]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'signboards/form', ['title'=>'Cadastrar placa','signboard'=>[],'errors'=>[],'properties'=>$this->service->repository()->properties(),'users'=>$this->service->repository()->activeUsers(),'action'=>'/signboards','editing'=>false,'materials'=>SignboardService::MATERIALS,'dealTypes'=>SignboardService::DEAL_TYPES]);
    }

    public function store(Request $request, Response $response): Response
    {
        return $this->save($request,$response);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id=(int)$args['id']; $row=$this->service->repository()->find($id);
        if (!$row || $row['removed_at'] !== null) return $response->withHeader('Location','/signboards')->withStatus(302);
        return $this->render($response,'signboards/form',['title'=>'Editar placa','signboard'=>$row,'errors'=>[],'properties'=>$this->service->repository()->properties(),'users'=>$this->service->repository()->activeUsers(),'action'=>'/signboards/'.$id,'editing'=>true,'materials'=>SignboardService::MATERIALS,'dealTypes'=>SignboardService::DEAL_TYPES]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return $this->save($request,$response,(int)$args['id']);
    }

    private function save(Request $request, Response $response, ?int $id=null): Response
    {
        $input=(array)($request->getParsedBody() ?? []);
        $result=$this->service->save($input,(int)(AuthService::user()['id'] ?? 0),$id);
        if (!$result['success']) return $this->render($response,'signboards/form',['title'=>$id?'Editar placa':'Cadastrar placa','signboard'=>$input,'errors'=>$result['errors'],'properties'=>$this->service->repository()->properties(),'users'=>$this->service->repository()->activeUsers(),'action'=>$id?'/signboards/'.$id:'/signboards','editing'=>$id!==null,'materials'=>SignboardService::MATERIALS,'dealTypes'=>SignboardService::DEAL_TYPES])->withStatus(422);
        SessionManager::flash('success',$id?'Cadastro da placa atualizado.':'Placa cadastrada e histórico iniciado.');
        return $response->withHeader('Location','/signboards/'.$result['signboard_id'])->withStatus(302);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return $this->renderDetail($response,(int)$args['id']);
    }

    public function assignForm(Request $request, Response $response, array $args): Response
    {
        $id=(int)$args['id'];
        $row=$this->service->repository()->find($id);
        if (!$row) return $response->withHeader('Location','/signboards')->withStatus(302);
        if ($row['board_stage']!=='disponivel' || $row['property_id']!==null) {
            SessionManager::flash('error','A placa precisa estar Disponível e sem imóvel vinculado.');
            return $response->withHeader('Location','/signboards')->withStatus(302);
        }
        return $this->renderAssignment($response,$row,[],null);
    }

    public function assign(Request $request, Response $response, array $args): Response
    {
        $id=(int)$args['id'];
        $body=(array)($request->getParsedBody() ?? []);
        $result=$this->service->assignToProperty($id,$body['property_id'] ?? null,(int)(AuthService::user()['id'] ?? 0));
        if (!$result['success']) {
            $row=$this->service->repository()->find($id);
            if (!$row) return $response->withHeader('Location','/signboards')->withStatus(302);
            if ($row['board_stage']!=='disponivel' || $row['property_id']!==null) {
                SessionManager::flash('error',$result['error']);
                return $response->withHeader('Location','/signboards')->withStatus(302);
            }
            return $this->renderAssignment($response,$row,['property_id'=>$result['error']],$body['property_id'] ?? null)->withStatus(422);
        }
        SessionManager::flash('success','Placa vinculada ao imóvel e movida para Usadas.');
        return $response->withHeader('Location','/signboards/'.$id)->withStatus(302);
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

    public function changeStage(Request $request, Response $response, array $args): Response
    {
        $body=(array)($request->getParsedBody() ?? []);
        $id=(int)$args['id'];
        $result=$this->service->changeStage($id,$this->inputText($body,'board_stage'),(int)(AuthService::user()['id'] ?? 0));
        SessionManager::flash($result['success']?'success':'error',$result['success']?'Etapa atualizada.':$result['error']);
        return $response->withHeader('Location','/signboards')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $result=$this->service->archive((int)$args['id'],(int)(AuthService::user()['id'] ?? 0));
        SessionManager::flash($result['success']?'success':'error',$result['success']?'Placa excluída do quadro. O histórico foi mantido para auditoria.':$result['error']);
        return $response->withHeader('Location','/signboards')->withStatus(302);
    }

    private function renderDetail(Response $response, int $id, int $status=200, ?string $actionError=null, array $actionInput=[], ?string $actionKind=null): Response
    {
        $row=$this->service->repository()->find($id);
        if (!$row) return $response->withHeader('Location','/signboards')->withStatus(302);
        return $this->render($response,'signboards/show',['title'=>'Placa · '.($row['property_code'] ?? 'sem imóvel'),'signboard'=>$row,'movements'=>$this->service->repository()->movements($id),'statuses'=>SignboardService::STATUSES,'actionError'=>$actionError,'actionInput'=>$actionInput,'actionKind'=>$actionKind])->withStatus($status);
    }

    private function renderAssignment(Response $response,array $row,array $errors=[],mixed $selectedPropertyId=null): Response
    {
        return $this->render($response,'signboards/assign',['title'=>'Colocar placa','signboard'=>$row,'properties'=>$this->service->repository()->properties(),'errors'=>$errors,'selectedPropertyId'=>$selectedPropertyId]);
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
