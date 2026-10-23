<?php

declare(strict_types=1);

namespace App\Modules\Properties\Controllers;

use App\Auth\AuthService;
use App\Auth\Session\SessionManager;
use App\Modules\Properties\Services\PropertyService;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PropertiesController
{
    private PropertyService $service;
    private Renderer $renderer;
    public function __construct(?PropertyService $service = null, ?Renderer $renderer = null) { $this->service = $service ?? new PropertyService(); $this->renderer = $renderer ?? new Renderer(); }

    public function index(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $filters = ['q' => trim((string)($q['q'] ?? '')), 'status' => trim((string)($q['status'] ?? '')), 'type' => trim((string)($q['type'] ?? '')), 'city' => trim((string)($q['city'] ?? ''))];
        $result = $this->service->repository()->paginate(max(1, (int)($q['page'] ?? 1)), max(1, min(100, (int)($q['per_page'] ?? 15))), $filters);
        return $this->render($response, 'properties/index', ['title' => 'Imóveis', 'properties' => $result['data'], 'total' => $result['total'], 'page' => $result['page'], 'perPage' => $result['per_page'], 'totalPages' => $result['total_pages'], 'filters' => $filters, 'cities' => $this->service->repository()->cities(), 'types' => PropertyService::TYPES, 'statuses' => PropertyService::STATUSES]);
    }

    public function create(Request $request, Response $response): Response
    { return $this->render($response, 'properties/form', ['title' => 'Novo imóvel', 'property' => [], 'errors' => [], 'types' => PropertyService::TYPES, 'statuses' => PropertyService::STATUSES, 'intentions' => PropertyService::INTENTIONS, 'aspirations' => PropertyService::ASPIRATIONS, 'action' => '/properties']); }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $property = $this->service->repository()->find((int)$args['id']);
        if (!$property) return $response->withHeader('Location', '/properties')->withStatus(302);
        return $this->render($response, 'properties/form', ['title' => 'Editar imóvel', 'property' => $property, 'errors' => [], 'types' => PropertyService::TYPES, 'statuses' => PropertyService::STATUSES, 'intentions' => PropertyService::INTENTIONS, 'aspirations' => PropertyService::ASPIRATIONS, 'action' => '/properties/' . (int)$args['id']]);
    }

    public function store(Request $request, Response $response): Response { return $this->save($request, $response); }
    public function update(Request $request, Response $response, array $args): Response { return $this->save($request, $response, (int)$args['id']); }

    private function save(Request $request, Response $response, ?int $id = null): Response
    {
        $input = (array)($request->getParsedBody() ?? []);
        $result = $this->service->save($input, (int)(AuthService::user()['id'] ?? 0), $id);
        if (!$result['success']) return $this->render($response, 'properties/form', ['title' => $id ? 'Editar imóvel' : 'Novo imóvel', 'property' => $input, 'errors' => $result['errors'], 'types' => PropertyService::TYPES, 'statuses' => PropertyService::STATUSES, 'intentions' => PropertyService::INTENTIONS, 'aspirations' => PropertyService::ASPIRATIONS, 'action' => $id ? '/properties/' . $id : '/properties'])->withStatus(422);
        SessionManager::flash('success', $id ? 'Imóvel atualizado.' : 'Imóvel cadastrado.');
        return $response->withHeader('Location', '/properties/' . $result['property_id'])->withStatus(302);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $property = $this->service->repository()->find($id);
        if (!$property) return $response->withHeader('Location', '/properties')->withStatus(302);
        $query = trim((string)($request->getQueryParams()['lead_q'] ?? ''));
        if (empty($property['listing_url'])) $property['listing_url'] = 'https://www.pinciara.com.br/imovel/' . rawurlencode($property['code']);
        return $this->render($response, 'properties/show', ['title' => $property['code'] . ' — Imóvel', 'property' => $property, 'types' => PropertyService::TYPES, 'statuses' => PropertyService::STATUSES, 'intentions' => PropertyService::INTENTIONS, 'aspirations' => PropertyService::ASPIRATIONS, 'leads' => $this->service->repository()->relatedLeads($id), 'leadSearch' => $query, 'leadOptions' => $query === '' ? [] : $this->service->repository()->searchLeads($query)]);
    }

    public function associate(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id']; $body = (array)($request->getParsedBody() ?? []);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($body['lead_ids'] ?? [])), static fn($value) => $value > 0)));
        if (isset($body['lead_id']) && (int)$body['lead_id'] > 0) $ids[] = (int)$body['lead_id'];
        $ids = array_values(array_unique($ids)); $added = 0; $duplicates = 0;
        foreach ($ids as $leadId) { $result = $this->service->associate($id, $leadId, (string)($body['interest_type'] ?? 'interesse'), (string)($body['notes'] ?? ''), (int)(AuthService::user()['id'] ?? 0)); if (!$result['success']) SessionManager::flash('error', $result['error']); elseif ($result['duplicate']) $duplicates++; else $added++; }
        if ($added) SessionManager::flash('success', $added . ' vínculo(s) criado(s).');
        if ($duplicates) SessionManager::flash('info', $duplicates . ' lead(s) já estavam vinculados; nenhum duplicado foi criado.');
        if (!$ids) SessionManager::flash('error', 'Selecione ao menos um lead para vincular.');
        return $response->withHeader('Location', '/properties/' . $id)->withStatus(302);
    }

    public function disassociate(Request $request, Response $response, array $args): Response
    {
        $propertyId = (int)$args['id']; $leadId = (int)$args['leadId'];
        if ($this->service->disassociate($propertyId, $leadId, (int)(AuthService::user()['id'] ?? 0))) SessionManager::flash('success', 'Vínculo removido e registrado no histórico.');
        else SessionManager::flash('info', 'O vínculo já não existia.');
        return $response->withHeader('Location', '/properties/' . $propertyId)->withStatus(302);
    }

    private function render(Response $response, string $template, array $data): Response
    { $response->getBody()->write($this->renderer->render($template, $data)); return $response; }
}
