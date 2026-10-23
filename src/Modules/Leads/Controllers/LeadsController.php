<?php

declare(strict_types=1);

namespace App\Modules\Leads\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Leads\Services\FollowUpService;
use App\Modules\Leads\Services\InteractionService;
use App\Modules\Leads\Services\LeadService;
use App\Modules\Leads\Services\PropertySuggestionService;
use App\Modules\Leads\Support\LeadNormalizer;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class LeadsController
{
    private LeadService $service;
    private Renderer $renderer;
    private InteractionService $interactionService;
    private FollowUpService $followUpService;

    public function __construct(
        ?LeadService $service = null,
        ?Renderer $renderer = null,
        ?InteractionService $interactionService = null,
        ?FollowUpService $followUpService = null
    ) {
        $this->service = $service ?? new LeadService();
        $this->renderer = $renderer ?? new Renderer();
        $this->interactionService = $interactionService ?? new InteractionService();
        $this->followUpService = $followUpService ?? new FollowUpService();
    }

    /**
     * Listagem paginada de leads com busca, filtros de estágio/corretor/origem e suporte a HTMX.
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        $page = max(1, (int)($queryParams['page'] ?? 1));
        $perPage = max(1, min(100, (int)($queryParams['per_page'] ?? 15)));

        $filters = [
            'q' => trim((string)($queryParams['q'] ?? '')),
            'stage' => trim((string)($queryParams['stage'] ?? '')),
            'user_id' => $queryParams['user_id'] ?? '',
            'source' => trim((string)($queryParams['source'] ?? '')),
        ];

        $repo = $this->service->getRepository();
        $pagination = $repo->paginate($page, $perPage, $filters);
        $totalInDatabase = $repo->count([]);
        $stageCounts = $repo->countByStage();
        $brokers = $repo->getActiveBrokers();

        $viewData = [
            'title' => 'Gestão de Leads e Oportunidades',
            'leads' => $pagination['data'],
            'total' => $pagination['total'],
            'totalInDatabase' => $totalInDatabase,
            'page' => $pagination['page'],
            'perPage' => $pagination['per_page'],
            'totalPages' => $pagination['total_pages'],
            'filters' => $filters,
            'stages' => PipelineManager::STAGES,
            'stageCounts' => $stageCounts,
            'brokers' => $brokers,
            'sources' => LeadNormalizer::ALLOWED_SOURCES,
            'properties' => \App\Database\Connection::getPdo()->query('SELECT id, code, title FROM properties ORDER BY code')->fetchAll(\PDO::FETCH_ASSOC),
            'formatPhone' => [LeadNormalizer::class, 'formatPhone'],
            'getStageBadge' => [PipelineManager::class, 'getBadgeClass'],
            'getStageLabel' => [PipelineManager::class, 'getLabel'],
        ];

        // Se a requisição veio do HTMX para atualizar apenas a tabela/resultados
        if ($request->hasHeader('HX-Request')) {
            $html = $this->renderer->render('leads/partials/table', $viewData, false);
            $response->getBody()->write($html);
            return $response;
        }

        $html = $this->renderer->render('leads/index', $viewData);
        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Exibe o formulário de cadastro de novo lead.
     */
    public function create(Request $request, Response $response): Response
    {
        $repo = $this->service->getRepository();
        $currentUser = AuthService::user();

        $html = $this->renderer->render('leads/create', [
            'title' => 'Novo Lead — Plataforma Pinciara',
            'brokers' => $repo->getActiveBrokers(),
            'sources' => LeadNormalizer::ALLOWED_SOURCES,
            'properties' => \App\Database\Connection::getPdo()->query('SELECT id, code, title FROM properties ORDER BY code')->fetchAll(\PDO::FETCH_ASSOC),
            'currentUser' => $currentUser,
            'values' => [
                'name' => '',
                'email' => '',
                'phone' => '',
                'source' => 'manual',
                'user_id' => $currentUser['id'] ?? '',
                'notes' => '',
                'property_code' => '',
            ],
            'errors' => [],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Processa a criação do lead com validação no servidor e normalização.
     */
    public function store(Request $request, Response $response): Response
    {
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user();
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->service->createLead($body, $actorId);

        if (!$result['success']) {
            $repo = $this->service->getRepository();
            $html = $this->renderer->render('leads/create', [
                'title' => 'Novo Lead — Plataforma Pinciara',
                'brokers' => $repo->getActiveBrokers(),
                'sources' => LeadNormalizer::ALLOWED_SOURCES,
                'properties' => \App\Database\Connection::getPdo()->query('SELECT id, code, title FROM properties ORDER BY code')->fetchAll(\PDO::FETCH_ASSOC),
                'currentUser' => $currentUser,
                'values' => $body,
                'errors' => $result['errors'] ?? [],
            ]);

            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        SessionManager::flash('success', 'Lead cadastrado com sucesso no pipeline comercial!');
        return $response->withHeader('Location', '/leads/' . $result['lead_id'])->withStatus(302);
    }

    /**
     * Visualização detalhada (ficha operacional) do lead, pipeline, imóveis associados e histórico.
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $repo = $this->service->getRepository();
        $lead = $repo->findById($leadId);

        if (!$lead) {
            throw new HttpNotFoundException($request, "Lead #{$leadId} não encontrado.");
        }

        $currentStage = (string)$lead['stage'];
        $allowedNextStages = PipelineManager::getAllowedNextStages($currentStage);
        $assignments = $repo->getAssignmentsHistory($leadId);
        $associatedProperties = $repo->getAssociatedProperties($leadId);
        $availableProperties = $repo->getAvailableProperties($leadId);
        $propertySearch = (new PropertySuggestionService())->forLead($leadId);
        $brokers = $repo->getActiveBrokers();

        $interactions = $this->interactionService->getChronologicalInteractions($leadId);
        $activeFollowUp = $this->followUpService->getRepository()->getActivePendingForLead($leadId);
        $followUpsHistory = $this->followUpService->getRepository()->getAllForLead($leadId);

        $html = $this->renderer->render('leads/show', [
            'title' => "Lead: {$lead['name']} — Plataforma Pinciara",
            'lead' => $lead,
            'stages' => PipelineManager::STAGES,
            'currentStage' => $currentStage,
            'allowedNextStages' => $allowedNextStages,
            'lossReasons' => PipelineManager::LOSS_REASONS,
            'assignments' => $assignments,
            'associatedProperties' => $associatedProperties,
            'availableProperties' => $availableProperties,
            'propertySuggestions' => $propertySearch['suggestions'],
            'propertySearch' => $propertySearch['search'],
            'brokers' => $brokers,
            'interactions' => $interactions,
            'activeFollowUp' => $activeFollowUp,
            'followUpsHistory' => $followUpsHistory,
            'sources' => LeadNormalizer::ALLOWED_SOURCES,
            'properties' => \App\Database\Connection::getPdo()->query('SELECT id, code, title FROM properties ORDER BY code')->fetchAll(\PDO::FETCH_ASSOC),
            'formatPhone' => [LeadNormalizer::class, 'formatPhone'],
            'getStageBadge' => [PipelineManager::class, 'getBadgeClass'],
            'getStageLabel' => [PipelineManager::class, 'getLabel'],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Exibe o formulário de edição de dados cadastrais.
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $repo = $this->service->getRepository();
        $lead = $repo->findById($leadId);

        if (!$lead) {
            throw new HttpNotFoundException($request, "Lead #{$leadId} não encontrado.");
        }

        $html = $this->renderer->render('leads/edit', [
            'title' => "Editar Lead: {$lead['name']} — Plataforma Pinciara",
            'lead' => $lead,
            'sources' => LeadNormalizer::ALLOWED_SOURCES,
            'properties' => \App\Database\Connection::getPdo()->query('SELECT id, code, title FROM properties ORDER BY code')->fetchAll(\PDO::FETCH_ASSOC),
            'values' => [
                'name' => $lead['name'],
                'email' => $lead['email'] ?? '',
                'phone' => $lead['phone'] ?? '',
                'source' => $lead['source'],
                'notes' => $lead['notes'] ?? '',
                'property_code' => ($repo->getAssociatedProperties($leadId)[0]['code'] ?? ''),
            ],
            'errors' => [],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Atualiza os dados cadastrais do lead com validação e auditoria.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user();
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->service->updateLead($leadId, $body, $actorId);

        if (!$result['success']) {
            $repo = $this->service->getRepository();
            $lead = $repo->findById($leadId);

            $html = $this->renderer->render('leads/edit', [
                'title' => "Editar Lead — Plataforma Pinciara",
                'lead' => $lead,
                'sources' => LeadNormalizer::ALLOWED_SOURCES,
                'properties' => \App\Database\Connection::getPdo()->query('SELECT id, code, title FROM properties ORDER BY code')->fetchAll(\PDO::FETCH_ASSOC),
                'values' => $body,
                'errors' => $result['errors'] ?? [],
            ]);

            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        SessionManager::flash('success', 'Dados do lead atualizados com sucesso!');
        return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
    }

    public function whatsapp(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $pdo = \App\Database\Connection::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM leads WHERE id=:id'); $stmt->execute([':id'=>$id]); $contact=$stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$contact) throw new HttpNotFoundException($request, 'Lead não encontrado.');
        $props = $this->service->getRepository()->getAssociatedProperties($id);
        $history = $pdo->prepare("SELECT w.*,u.name AS user_name FROM whatsapp_contact_logs w LEFT JOIN users u ON u.id=w.user_id WHERE contact_type='lead' AND contact_id=:id ORDER BY w.id DESC LIMIT 20"); $history->execute([':id'=>$id]);
        return $this->renderWhatsapp($response, 'lead', $contact, $props, $history->fetchAll(\PDO::FETCH_ASSOC), '/leads/'.$id.'/whatsapp');
    }

    public function saveWhatsappResult(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $contact = $this->service->getRepository()->findById($leadId);
        if (!$contact) throw new HttpNotFoundException($request, 'Lead não encontrado.');
        $this->recordWhatsapp('lead', $leadId, (array)($request->getParsedBody() ?? []));
        return $response->withHeader('Location', '/leads/'.$leadId.'/whatsapp')->withStatus(302);
    }

    private function renderWhatsapp(Response $response, string $type, array $contact, array $properties, array $history, string $action): Response
    {
        $response->getBody()->write($this->renderer->render('contacts/whatsapp', ['title'=>'WhatsApp — '.$contact['name'], 'contactType'=>$type, 'contact'=>$contact, 'properties'=>$properties, 'history'=>$history, 'action'=>$action, 'rules'=>['1º contato','Pós-visita','Nova oportunidade','Follow-up','Pós-fechamento de contrato'], 'returnUrl'=>str_starts_with($action,'/leads/') ? substr($action,0,strrpos($action,'/whatsapp')) : '/owners']));
        return $response;
    }

    private function recordWhatsapp(string $type, int $id, array $body): void
    {
        $rule = trim((string)($body['rule'] ?? '')); $text = trim((string)($body['message'] ?? '')); $result = (string)($body['result'] ?? '');
        $allowed = ['1º contato','Pós-visita','Nova oportunidade','Follow-up','Pós-fechamento de contrato'];
        if (!in_array($rule,$allowed,true) || $text==='' || !mb_check_encoding($text,'UTF-8') || mb_strlen($text)>4000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$text) || !in_array($result,['confirmed','not_sent','cancelled'],true)) { SessionManager::flash('error','Informe a régua, a mensagem válida (até 4.000 caracteres) e o resultado do contato.'); return; }
        $pdo=\App\Database\Connection::getPdo(); $s=$pdo->prepare('INSERT INTO whatsapp_contact_logs(contact_type,contact_id,rule,message,result,user_id) VALUES(:type,:id,:rule,:message,:result,:user)');
        $labels=['confirmed'=>'confirmado pelo corretor','not_sent'=>'não enviado','cancelled'=>'cancelado'];
        $s->execute([':type'=>$type,':id'=>$id,':rule'=>$rule,':message'=>$text,':result'=>$labels[$result],':user'=>(int)(AuthService::user()['id']??0)]);
        SessionManager::flash('success','Resultado registrado no histórico.');
    }


    /**
     * Altera o estágio do lead no pipeline, validando transições e motivo de perda.
     */
    public function changeStage(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $newStage = trim((string)($body['stage'] ?? ''));
        $lossReason = trim((string)($body['loss_reason'] ?? ''));

        $currentUser = AuthService::user();
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->service->changeStage($leadId, $newStage, $lossReason, $actorId);

        if (!$result['success']) {
            SessionManager::flash('error', $result['error'] ?? 'Não foi possível alterar o estágio.');
            return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
        }

        $stageLabel = PipelineManager::getLabel($newStage);
        SessionManager::flash('success', "Estágio do lead alterado com sucesso para: {$stageLabel}");
        return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
    }

    /**
     * Transfere o responsável pelo lead com justificativa obrigatória e auditoria.
     */
    public function reassign(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $toUserId = (int)($body['to_user_id'] ?? 0);
        $reason = trim((string)($body['reason'] ?? ''));

        $currentUser = AuthService::user();
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->service->reassignLead($leadId, $toUserId, $reason, $actorId);

        if (!$result['success']) {
            SessionManager::flash('error', $result['error'] ?? 'Não foi possível trocar o responsável.');
            return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
        }

        SessionManager::flash('success', 'Responsável pelo lead transferido com sucesso!');
        return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
    }

    /**
     * Associa um imóvel ao lead (relação N:N).
     */
    public function associateProperty(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $propertyId = (int)($body['property_id'] ?? 0);
        $interestType = trim((string)($body['interest_type'] ?? 'interesse'));
        $notes = trim((string)($body['notes'] ?? ''));

        $currentUser = AuthService::user();
        $actorId = (int)($currentUser['id'] ?? 0);

        if ($propertyId <= 0) {
            SessionManager::flash('error', 'Selecione um imóvel válido para vincular.');
            return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
        }

        $result = $this->service->associateProperty($leadId, $propertyId, $interestType, $notes, $actorId);

        if (!$result['success']) {
            SessionManager::flash('error', $result['error'] ?? 'Falha ao vincular imóvel.');
            return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
        }

        if (!empty($result['duplicate'])) {
            SessionManager::flash('info', 'Este imóvel já estava vinculado ao lead; nenhum duplicado foi criado.');
            return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
        }

        SessionManager::flash('success', 'Imóvel vinculado com sucesso ao lead!');
        return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
    }

    /**
     * Remove o vínculo N:N entre um lead e um imóvel.
     */
    public function disassociateProperty(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $propertyId = (int)$args['propertyId'];
        $currentUser = AuthService::user();
        $actorId = (int)($currentUser['id'] ?? 0);

        $this->service->disassociateProperty($leadId, $propertyId, $actorId);

        SessionManager::flash('info', 'Vínculo do imóvel removido.');
        return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
    }

    public function savePropertySearch(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $parseMoney = static function (mixed $value): ?float {
            $value = trim((string)$value);
            if ($value === '') return null;
            $value = str_replace(['R$', ' '], '', $value);
            if (str_contains($value, ',')) $value = str_replace(',', '.', str_replace('.', '', $value));
            elseif (substr_count($value, '.') > 1) $value = str_replace('.', '', $value);
            return is_numeric($value) ? (float)$value : NAN;
        };
        $min = $parseMoney($body['search_price_min'] ?? '');
        $max = $parseMoney($body['search_price_max'] ?? '');
        if (($min !== null && !is_finite($min)) || ($max !== null && !is_finite($max))) {
            SessionManager::flash('error', 'Informe valores de preço válidos.');
            return $response->withHeader('Location', "/leads/{$leadId}#property-search")->withStatus(302);
        }
        $result = (new PropertySuggestionService())->save(
            $leadId,
            (string)($body['search_regions'] ?? ''),
            $min,
            $max,
            (int)(AuthService::user()['id'] ?? 0)
        );
        SessionManager::flash($result['success'] ? 'success' : 'error', $result['success'] ? 'Preferências de busca atualizadas.' : ($result['error'] ?? 'Não foi possível salvar as preferências.'));
        return $response->withHeader('Location', "/leads/{$leadId}#property-search")->withStatus(302);
    }

    /**
     * Exclui um lead (exclusivo para perfil Administrador).
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $currentUser = AuthService::user();
        $actorId = (int)($currentUser['id'] ?? 0);
        $actorRole = (string)($currentUser['role'] ?? '');

        if ($actorRole !== 'admin') {
            $html = $this->renderer->render('errors/403', [
                'title' => '403 — Acesso Proibido',
                'message' => 'Apenas administradores possuem autorização para excluir registros de leads.',
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(403);
        }

        $result = $this->service->deleteLead($leadId, $actorId, $actorRole);

        if (!$result['success']) {
            SessionManager::flash('error', $result['error'] ?? 'Falha ao excluir lead.');
            return $response->withHeader('Location', "/leads/{$leadId}")->withStatus(302);
        }

        SessionManager::flash('success', 'Lead excluído permanentemente da base.');
        return $response->withHeader('Location', '/leads')->withStatus(302);
    }

    /**
     * Registra uma nova interação manual ou nota de contato no lead.
     */
    public function storeInteraction(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user() ?? [];
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->interactionService->createInteraction($leadId, $body, $actorId);

        if (!$result['success']) {
            SessionManager::flash('error', $result['errors']['notes'] ?? $result['error'] ?? 'Erro ao registrar interação.');
            return $response->withHeader('Location', "/leads/{$leadId}#interactions-section")->withStatus(302);
        }

        SessionManager::flash('success', 'Interação registrada com sucesso no histórico cronológico!');
        return $response->withHeader('Location', "/leads/{$leadId}#interactions-section")->withStatus(302);
    }

    /**
     * Agenda um novo follow-up para o lead.
     */
    public function storeFollowUp(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user() ?? [];
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->followUpService->scheduleFollowUp($leadId, $body, $actorId);

        if (!$result['success']) {
            $validationErrors = $result['errors'] ?? [];
            $message = $result['error'] ?? (array_values($validationErrors)[0] ?? 'Erro ao agendar retorno.');
            SessionManager::flash('error', $message);
            return $response->withHeader('Location', "/leads/{$leadId}#follow-up-section")->withStatus(302);
        }

        SessionManager::flash('success', 'Retorno de follow-up agendado com sucesso!');
        return $response->withHeader('Location', "/leads/{$leadId}#follow-up-section")->withStatus(302);
    }

    /**
     * Conclui um follow-up registrando o resultado do contato no contexto do lead.
     */
    public function completeFollowUp(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $followUpId = (int)$args['followUpId'];
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user() ?? [];
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->followUpService->completeFollowUp($followUpId, $body, $actorId);

        if (!$result['success']) {
            $validationErrors = $result['errors'] ?? [];
            $message = $result['error'] ?? (array_values($validationErrors)[0] ?? 'Erro ao concluir retorno.');
            SessionManager::flash('error', $message);
            return $response->withHeader('Location', "/leads/{$leadId}#follow-up-section")->withStatus(302);
        }

        SessionManager::flash('success', 'Resultado do contato registrado e retorno concluído!');
        return $response->withHeader('Location', "/leads/{$leadId}#follow-up-section")->withStatus(302);
    }

    /**
     * Reagenda um follow-up no contexto do lead.
     */
    public function rescheduleFollowUp(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];
        $followUpId = (int)$args['followUpId'];
        $body = (array)($request->getParsedBody() ?? []);
        $currentUser = AuthService::user() ?? [];
        $actorId = (int)($currentUser['id'] ?? 0);

        $result = $this->followUpService->rescheduleFollowUp($followUpId, $body, $actorId);

        if (!$result['success']) {
            $validationErrors = $result['errors'] ?? [];
            $message = $result['error'] ?? (array_values($validationErrors)[0] ?? 'Erro ao reagendar retorno.');
            SessionManager::flash('error', $message);
            return $response->withHeader('Location', "/leads/{$leadId}#follow-up-section")->withStatus(302);
        }

        SessionManager::flash('success', 'Retorno reagendado com sucesso!');
        return $response->withHeader('Location', "/leads/{$leadId}#follow-up-section")->withStatus(302);
    }
}
