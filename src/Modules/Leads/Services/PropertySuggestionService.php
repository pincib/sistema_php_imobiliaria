<?php

declare(strict_types=1);

namespace App\Modules\Leads\Services;

use App\Audit\AuditService;
use App\Database\Connection;
use PDO;

final class PropertySuggestionService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
    }

    /** @return array{suggestions: list<array<string, mixed>>, search: array<string, mixed>} */
    public function forLead(int $leadId): array
    {
        $leadStmt = $this->pdo->prepare('SELECT id, search_regions, search_price_min, search_price_max FROM leads WHERE id = :id');
        $leadStmt->execute([':id' => $leadId]);
        $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);
        if (!$lead) return ['suggestions' => [], 'search' => ['ready' => false, 'message' => 'Lead não encontrado.']];

        $linkedStmt = $this->pdo->prepare('SELECT p.address, p.neighborhood, p.city, COALESCE(p.price, p.sale_price, p.rent_price) AS price FROM lead_properties lp JOIN properties p ON p.id = lp.property_id WHERE lp.lead_id = :id');
        $linkedStmt->execute([':id' => $leadId]);
        $linked = $linkedStmt->fetchAll(PDO::FETCH_ASSOC);

        $manualRegions = $this->parseRegions((string)($lead['search_regions'] ?? ''));
        $regions = $manualRegions;
        $regionSource = $manualRegions ? 'manual' : 'inferred';
        if (!$regions) {
            foreach ($linked as $property) {
                if (!empty($property['city'])) $regions[] = (string)$property['city'];
            }
            $regions = array_values(array_unique($regions));
        }

        $min = $lead['search_price_min'] !== null ? (float)$lead['search_price_min'] : null;
        $max = $lead['search_price_max'] !== null ? (float)$lead['search_price_max'] : null;
        $priceSource = ($min !== null || $max !== null) ? 'manual' : 'inferred';
        if ($min === null && $max === null) {
            $linkedPrices = array_values(array_filter(array_map(static fn(array $p): ?float => $p['price'] === null ? null : (float)$p['price'], $linked), static fn(?float $p): bool => $p !== null && $p > 0));
            if ($linkedPrices) {
                $min = min($linkedPrices);
                $max = max($linkedPrices);
            }
        }

        $search = [
            'ready' => false,
            'regions' => $regions,
            'region_source' => $regionSource,
            'price_min' => $min,
            'price_max' => $max,
            'price_source' => $priceSource,
            'manual_regions' => (string)($lead['search_regions'] ?? ''),
            'manual_price_min' => $lead['search_price_min'],
            'manual_price_max' => $lead['search_price_max'],
        ];
        if (!$regions) {
            $search['message'] = 'Informe uma região ou vincule um imóvel com cidade cadastrada para gerar sugestões.';
            return ['suggestions' => [], 'search' => $search];
        }

        $search['ready'] = true;
        $cityStmt = $this->pdo->query("SELECT DISTINCT city FROM properties WHERE city IS NOT NULL AND city<>''");
        $knownCities = array_map(fn(string $city): string => $this->normalize($city), $cityStmt->fetchAll(PDO::FETCH_COLUMN));
        $propertiesStmt = $this->pdo->prepare('SELECT p.*, COALESCE(p.price, p.sale_price, p.rent_price) AS suggestion_price FROM properties p WHERE NOT EXISTS (SELECT 1 FROM lead_properties lp WHERE lp.lead_id = :lead_id AND lp.property_id = p.id)');
        $propertiesStmt->execute([':lead_id' => $leadId]);
        $suggestions = [];
        foreach ($propertiesStmt->fetchAll(PDO::FETCH_ASSOC) as $property) {
            if ($property['suggestion_price'] === null || (float)$property['suggestion_price'] <= 0) continue;
            $price = (float)$property['suggestion_price'];
            if (($min !== null && $price < $min) || ($max !== null && $price > $max)) continue;
            $region = $this->regionMatch($property, $regions, $linked, $knownCities, $manualRegions !== []);
            if ($region === null) continue;
            $distance = 0.0;
            $property['suggestion_price'] = $price;
            $property['region_reason'] = $region['reason'];
            $property['region_score'] = $region['score'];
            $property['price_distance'] = $distance;
            $property['price_reason'] = ($min === null && $max === null) ? 'Preço sem limite definido' : 'Dentro da faixa informada';
            $suggestions[] = $property;
        }
        usort($suggestions, static fn(array $a, array $b): int => ($b['region_score'] <=> $a['region_score']) ?: ($a['price_distance'] <=> $b['price_distance']) ?: strcmp((string)$a['code'], (string)$b['code']));
        $search['message'] = $suggestions ? null : 'Nenhum imóvel disponível corresponde à região e à faixa de preço informadas.';
        return ['suggestions' => $suggestions, 'search' => $search];
    }

    /** @return array{success: bool, error?: string} */
    public function save(int $leadId, string $regions, ?float $min, ?float $max, int $actorId): array
    {
        $regions = trim($regions);
        if (mb_strlen($regions) > 500) return ['success' => false, 'error' => 'Informe regiões com até 500 caracteres.'];
        if (($min !== null && $min < 0) || ($max !== null && $max < 0)) return ['success' => false, 'error' => 'Os valores da faixa de preço devem ser positivos.'];
        if ($min !== null && $max !== null && $min > $max) return ['success' => false, 'error' => 'O valor mínimo não pode ser maior que o máximo.'];
        $stmt = $this->pdo->prepare('SELECT search_regions, search_price_min, search_price_max FROM leads WHERE id = :id');
        $stmt->execute([':id' => $leadId]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) return ['success' => false, 'error' => 'Lead não encontrado.'];
        $cleanRegions = $regions === '' ? null : $regions;
        $this->pdo->prepare('UPDATE leads SET search_regions=:regions, search_price_min=:min, search_price_max=:max, updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute([
            ':regions' => $cleanRegions, ':min' => $min, ':max' => $max, ':id' => $leadId,
        ]);
        AuditService::log('lead.property_search_preferences_updated', 'lead', (string)$leadId, $old, ['search_regions' => $cleanRegions, 'search_price_min' => $min, 'search_price_max' => $max], $actorId);
        return ['success' => true];
    }

    /** @param array<string, mixed> $property @param list<string> $regions @param list<array<string, mixed>> $linked @return array{score: int, reason: string}|null */
    private function regionMatch(array $property, array $regions, array $linked, array $knownCities, bool $hasManualRegions): ?array
    {
        $city = $this->normalize((string)($property['city'] ?? ''));
        if ($city === '') return null;
        $niteroi = $city === 'niteroi';
        $normalizedRegions = array_map(fn(string $region): string => $this->normalize($region), $regions);
        $cityCompatible = false;
        $manualCities = $hasManualRegions ? array_values(array_intersect(array_map(fn(string $region): string => $this->normalize($region), $regions), $knownCities)) : [];
        if ($manualCities) $cityCompatible = in_array($city, $manualCities, true);
        elseif ($hasManualRegions && $niteroi) {
            $neighborhood = $this->normalize((string)($property['neighborhood'] ?? ''));
            $address = $this->normalize((string)($property['address'] ?? ''));
            $cityCompatible = (bool)array_intersect($normalizedRegions, array_filter([$neighborhood, $address, $city]));
        }
        foreach ($linked as $reference) {
            $refCity = $this->normalize((string)($reference['city'] ?? ''));
            if (!$manualCities && !($hasManualRegions && $niteroi) && $refCity !== '' && $refCity === $city) $cityCompatible = true;
        }
        if (!$linked) {
            if ($niteroi) {
                $neighborhood = $this->normalize((string)($property['neighborhood'] ?? ''));
                $address = $this->normalize((string)($property['address'] ?? ''));
                foreach ($normalizedRegions as $region) {
                    if ($region !== '' && ($region === $neighborhood || $region === $address || $region === $city)) return ['score' => $region === $city ? 1 : 2, 'reason' => $region === $city ? 'Cidade compatível: Niterói' : 'Região informada: ' . (string)$property['neighborhood']];
                }
                return null;
            }
            return in_array($city, $normalizedRegions, true) ? ['score' => 2, 'reason' => 'Cidade informada: ' . (string)$property['city']] : null;
        }
        if (!$cityCompatible) return null;
        if (!$niteroi) return ['score' => 2, 'reason' => $manualCities ? 'Cidade informada: ' . (string)$property['city'] : 'Mesma cidade dos imóveis vinculados: ' . (string)$property['city']];

        $neighborhoodMatched = false;
        foreach ($linked as $reference) {
            if ($this->normalize((string)($reference['city'] ?? '')) !== $city) continue;
            $refAddress = $this->normalize((string)($reference['address'] ?? ''));
            $refNeighborhood = $this->normalize((string)($reference['neighborhood'] ?? ''));
            $propAddress = $this->normalize((string)($property['address'] ?? ''));
            $propNeighborhood = $this->normalize((string)($property['neighborhood'] ?? ''));
            if ($refAddress !== '' && $refNeighborhood !== '' && $refAddress === $propAddress && $refNeighborhood === $propNeighborhood) return ['score' => 4, 'reason' => 'Mesmo logradouro e bairro em Niterói'];
            if ($refNeighborhood !== '' && $refNeighborhood === $propNeighborhood) $neighborhoodMatched = true;
        }
        if ($neighborhoodMatched) return ['score' => 3, 'reason' => 'Mesmo bairro em Niterói'];
        foreach ($normalizedRegions as $region) {
            if ($region !== '' && ($region === $this->normalize((string)($property['neighborhood'] ?? '')) || $region === $this->normalize((string)($property['address'] ?? '')))) return ['score' => 3, 'reason' => 'Região informada em Niterói: ' . (string)($property['neighborhood'] ?? $property['address'])];
        }
        if (in_array($city, $manualCities, true)) return ['score' => 1, 'reason' => 'Cidade informada: Niterói'];
        return ['score' => 1, 'reason' => 'Mesma cidade dos imóveis vinculados: Niterói'];
    }

    /** @return list<string> */
    private function parseRegions(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/u', $value) ?: []), static fn(string $part): bool => $part !== ''));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $ascii === false ? $value : $ascii));
    }
}
