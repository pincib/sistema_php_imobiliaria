<?php

declare(strict_types=1);

namespace App\Modules\Properties\Services;

use App\Audit\AuditService;
use App\Modules\Properties\Repositories\PropertyRepository;

final class PropertyService
{
    public const TYPES = ['loja' => 'Loja', 'casa' => 'Casa', 'galpao' => 'Galpão', 'terreno' => 'Terreno', 'area' => 'Área', 'andar_corporativo' => 'Andar corporativo', 'apartamento' => 'Apartamento', 'cobertura' => 'Cobertura', 'ponto' => 'Ponto', 'predio' => 'Prédio', 'sala' => 'Sala', 'comercial' => 'Comercial'];
    public const STATUSES = ['disponivel' => 'Disponível', 'reservado' => 'Reservado', 'vendido' => 'Vendido', 'alugado' => 'Alugado', 'inativo' => 'Inativo'];
    public const INTENTIONS = ['venda' => 'Venda', 'locacao' => 'Locação', 'venda_locacao' => 'Venda/Locação'];
    public const ASPIRATIONS = ['residencial' => 'Residencial', 'comercial' => 'Comercial'];

    private PropertyRepository $repository;
    public function __construct(?PropertyRepository $repository = null) { $this->repository = $repository ?? new PropertyRepository(); }
    public function repository(): PropertyRepository { return $this->repository; }

    public function validate(array $input, ?int $id = null): array
    {
        $errors = [];
        $code = trim((string)($input['code'] ?? ''));
        if ($code === '' || mb_strlen($code) > 40) $errors['code'] = 'Informe um código de até 40 caracteres.';
        elseif ($this->repository->findByCode($code, $id)) $errors['code'] = 'Este código já está cadastrado.';
        $address = trim((string)($input['address'] ?? ''));
        if ($address === '' || mb_strlen($address) > 180) $errors['address'] = 'Informe um endereço de até 180 caracteres.';
        if (!isset(self::TYPES[(string)($input['type'] ?? '')])) $errors['type'] = 'Selecione um tipo válido.';
        if (!isset(self::STATUSES[(string)($input['status'] ?? '')])) $errors['status'] = 'Selecione um status válido.';
        $maps = trim((string)($input['google_maps_url'] ?? ''));
        if ($maps !== '' && (!$this->isHttpUrl($maps) || !$this->isGoogleMapsUrl($maps))) $errors['google_maps_url'] = 'Informe um link compartilhável válido do Google Maps.';
        foreach (['neighborhood' => 'Informe o bairro.', 'city' => 'Informe a cidade.'] as $field => $message) if (trim((string)($input[$field] ?? '')) === '') $errors[$field] = $message;
        foreach (['built_area' => true, 'land_area' => false] as $field => $positive) {
            $value = trim((string)($input[$field] ?? '')); $number = $this->number($value);
            if ($value !== '' && ($number === null || ($positive ? $number <= 0 : $number < 0))) $errors[$field] = $positive ? 'Informe uma área maior que zero.' : 'Informe uma área igual ou maior que zero.';
        }
        $intention = (string)($input['intention'] ?? '');
        if (!isset(self::INTENTIONS[$intention])) $errors['intention'] = 'Selecione uma intenção válida.';
        foreach (['sale_price', 'rent_price', 'condominium_price', 'iptu_price'] as $field) {
            $value = trim((string)($input[$field] ?? '')); $number = $this->number($value);
            if ($value !== '' && ($number === null || $number < 0)) $errors[$field] = 'Informe um valor monetário brasileiro válido e não negativo.';
        }
        if (!isset(self::ASPIRATIONS[(string)($input['aspiration'] ?? '')])) $errors['aspiration'] = 'Selecione uma pretensão válida.';
        $listingUrl = trim((string)($input['listing_url'] ?? ''));
        if ($listingUrl !== '' && !$this->isHttpUrl($listingUrl)) $errors['listing_url'] = 'Informe um link de anúncio válido iniciado por http:// ou https://.';
        return $errors;
    }

    public function data(array $input): array
    {
        $sale = $this->number(trim((string)($input['sale_price'] ?? '')));
        $rent = $this->number(trim((string)($input['rent_price'] ?? '')));
        $clean = static fn(string $key, int $max): ?string => ($v = trim(mb_substr((string)($input[$key] ?? ''), 0, $max))) === '' ? null : $v;
        $code = strtoupper(trim((string)$input['code']));
        return [':code' => $code, ':title' => trim((string)($input['address'] ?? $input['title'])), ':type' => $input['type'], ':price' => $sale ?? $rent, ':status' => $input['status'], ':address' => $clean('address', 255), ':neighborhood' => $clean('neighborhood', 120), ':city' => $clean('city', 120), ':characteristics' => $clean('characteristics', 4000), ':notes' => $clean('notes', 4000), ':google_maps_url' => $clean('google_maps_url', 1000), ':built_area' => $this->number(trim((string)($input['built_area'] ?? ''))), ':land_area' => $this->number(trim((string)($input['land_area'] ?? ''))), ':intention' => $input['intention'], ':sale_price' => $sale, ':rent_price' => $rent, ':aspiration' => $input['aspiration'], ':condominium_price' => $this->number(trim((string)($input['condominium_price'] ?? ''))), ':iptu_price' => $this->number(trim((string)($input['iptu_price'] ?? ''))), ':listing_url' => $clean('listing_url', 1000) ?? 'https://www.pinciara.com.br/imovel/' . rawurlencode($code)];
    }

    private function number(string $value): ?float
    {
        if ($value === '') return null;
        $value = trim(str_replace(["\u{00A0}", ' '], '', $value));
        if (str_contains($value, ',')) {
            if (!preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/', $value)) return null;
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        $number = (float)$value;
        return is_finite($number) ? $number : null;
    }

    private function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            && parse_url($url, PHP_URL_HOST) !== null;
    }

    private function isGoogleMapsUrl(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        return in_array($host, ['maps.google.com', 'google.com', 'www.google.com', 'maps.app.goo.gl', 'goo.gl'], true)
            && ($host === 'maps.app.goo.gl' || $host === 'goo.gl' || str_contains((string)parse_url($url, PHP_URL_PATH), '/maps'));
    }

    public function save(array $input, int $actorId, ?int $id = null): array
    {
        $errors = $this->validate($input, $id);
        if ($errors) return ['success' => false, 'errors' => $errors];
        $data = $this->data($input);
        $normalized = array_combine(['code','title','type','price','status','address','neighborhood','city','characteristics','notes','google_maps_url','built_area','land_area','intention','sale_price','rent_price','aspiration','condominium_price','iptu_price','listing_url'], array_values($data));
        if ($id === null) {
            $id = $this->repository->create($data);
            AuditService::log('property.create', 'property', (string)$id, null, $normalized, $actorId);
        } else {
            $old = $this->repository->find($id);
            if (!$old) return ['success' => false, 'errors' => ['general' => 'Imóvel não encontrado.']];
            $before = array_intersect_key($old, $normalized);
            $this->repository->update($id, $data);
            if ($before !== $normalized) AuditService::log('property.update', 'property', (string)$id, $before, $normalized, $actorId);
        }
        return ['success' => true, 'property_id' => $id];
    }

    public function associate(int $propertyId, int $leadId, string $interest, ?string $notes, int $actorId): array
    {
        $property = $this->repository->find($propertyId);
        if (!$property) return ['success' => false, 'error' => 'Imóvel não encontrado.'];
        $pdo = \App\Database\Connection::getPdo();
        $stmt = $pdo->prepare('SELECT id,name FROM leads WHERE id=:id');
        $stmt->execute([':id' => $leadId]);
        $leadRow = $stmt->fetch();
        if (!$leadRow) return ['success' => false, 'error' => 'Lead não encontrado.'];
        $valid = ['interesse', 'visitado', 'proposta'];
        if (!in_array($interest, $valid, true)) return ['success' => false, 'error' => 'Selecione um tipo de relação válido.'];
        $notes = $notes === null || trim($notes) === '' ? null : mb_substr(trim($notes), 0, 2000);
        if (!$this->repository->link($leadId, $propertyId, $interest, $notes)) return ['success' => true, 'duplicate' => true];
        AuditService::log('property.lead_associated', 'property', (string)$propertyId, null, ['lead_id' => $leadId, 'lead_name' => $leadRow['name'], 'interest_type' => $interest, 'notes' => $notes], $actorId);
        AuditService::log('lead.property_associated', 'lead', (string)$leadId, null, ['property_id' => $propertyId, 'property_code' => $property['code'], 'interest_type' => $interest, 'notes' => $notes], $actorId);
        return ['success' => true, 'duplicate' => false];
    }

    public function disassociate(int $propertyId, int $leadId, int $actorId): bool
    {
        $link = $this->repository->association($leadId, $propertyId);
        if (!$link || !$this->repository->unlink($leadId, $propertyId)) return false;
        $values = ['lead_id' => $leadId, 'lead_name' => $link['lead_name'], 'property_id' => $propertyId, 'property_code' => $link['code'], 'interest_type' => $link['interest_type'], 'notes' => $link['notes']];
        AuditService::log('property.lead_disassociated', 'property', (string)$propertyId, $values, null, $actorId);
        AuditService::log('lead.property_disassociated', 'lead', (string)$leadId, ['property_id' => $propertyId, 'property_code' => $link['code']], null, $actorId);
        return true;
    }
}
