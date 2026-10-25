<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Modules\Dashboard\Repositories\LeadsDashboardRepository;
use App\Modules\Dashboard\Repositories\OwnersDashboardRepository;
use App\Modules\Dashboard\Repositories\PropertiesDashboardRepository;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Properties\Services\PropertyService;
use App\Support\DateHelper;
use DateTimeImmutable;
use DateTimeZone;

/** Validates public dashboard filters and exposes domain-separated read models. */
final class DashboardDataService
{
    private LeadsDashboardRepository $leads;
    private PropertiesDashboardRepository $properties;
    private OwnersDashboardRepository $owners;

    public function __construct(
        ?LeadsDashboardRepository $leads = null,
        ?PropertiesDashboardRepository $properties = null,
        ?OwnersDashboardRepository $owners = null
    ) {
        $this->leads = $leads ?? new LeadsDashboardRepository();
        $this->properties = $properties ?? new PropertiesDashboardRepository();
        $this->owners = $owners ?? new OwnersDashboardRepository();
    }

    /** Compact data for the principal dashboard; all metrics use current links/stages. */
    public function principalSummary(): array
    {
        [$stages, $leadsError] = $this->capture(fn() => $this->leads->stageCounts(null, null), 'Leads');
        [$topProperties, $topPropertiesError] = $this->capture(fn() => $this->properties->topProperties([], 3), 'Imóveis: ranking');
        [$propertyTypes, $propertyTypesError] = $this->capture(fn() => $this->properties->leadCountsByType([], 3), 'Imóveis: tipos');
        [$ownerCount, $ownerCountError] = $this->capture(fn() => $this->owners->topOwners('count', [], 3), 'Proprietários: quantidade');
        [$ownerSale, $ownerSaleError] = $this->capture(fn() => $this->owners->topOwners('sale', [], 3), 'Proprietários: venda');
        [$ownerRent, $ownerRentError] = $this->capture(fn() => $this->owners->topOwners('rent', [], 3), 'Proprietários: aluguel');
        [$latestContact, $contactError] = $this->capture(fn() => $this->owners->latestConfirmedOwnerContactUtc(), 'Proprietários: contato');
        return [
            'leads' => [
                'proposal' => $stages === null ? null : ($stages[PipelineManager::STAGE_PROPOSTA] ?? 0),
                'closed' => $stages === null ? null : ($stages[PipelineManager::STAGE_GANHO] ?? 0),
                'lost' => $stages === null ? null : ($stages[PipelineManager::STAGE_PERDIDO] ?? 0),
            ],
            'properties' => [
                'top' => $topProperties,
                'types' => $propertyTypes,
            ],
            'owners' => [
                'count' => $ownerCount,
                'sale' => $ownerSale,
                'rent' => $ownerRent,
                'days_since_last_confirmed_contact' => $contactError === null ? $this->daysSinceLatestOwnerContact($latestContact) : null,
            ],
            'errors' => array_filter([
                'leads' => $leadsError,
                'properties.top' => $topPropertiesError,
                'properties.types' => $propertyTypesError,
                'owners.count' => $ownerCountError,
                'owners.sale' => $ownerSaleError,
                'owners.rent' => $ownerRentError,
                'owners.contact' => $contactError,
            ]),
        ];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function leadsDashboard(array $query): array
    {
        [$dates, $errors] = $this->dateFilters($query);
        $stage = $this->stringValue($query['stage'] ?? null);
        if ($stage !== '' && !PipelineManager::isValidStage($stage)) { $errors[] = 'O estágio informado foi ignorado por ser inválido.'; $stage = ''; }
        $stageFilter = $stage === '' ? null : $stage;
        [$counts, $metricsError] = $this->capture(fn() => $this->leads->stageCounts($dates['from'], $dates['to']), 'Leads: indicadores');
        [$leadList, $listError] = $this->capture(fn() => $this->leads->paginateLeads($dates['from'], $dates['to'], $stageFilter, $this->page($query['page'] ?? null)), 'Leads: lista');
        return [
            'filters' => ['date_from' => $dates['date_from'], 'date_to' => $dates['date_to'], 'stage' => $stage],
            'errors' => array_values(array_filter(array_merge($errors, [$metricsError, $listError]))),
            'metrics_error' => $metricsError,
            'list_error' => $listError,
            'stage_counts' => $counts ?? array_fill_keys(array_keys(PipelineManager::STAGES), null),
            'leads' => $leadList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15, 'pages' => 1],
        ];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function propertiesDashboard(array $query): array
    {
        [$dates, $errors] = $this->dateFilters($query);
        $filters = [
            'date_from_utc' => $dates['from'], 'date_to_exclusive_utc' => $dates['to'],
            'type' => $this->catalogFilter($query['type'] ?? null, array_merge(['unknown' => 'Tipo não identificado'], PropertyService::TYPES), 'Tipo', $errors),
            'status' => $this->catalogFilter($query['status'] ?? null, PropertyService::STATUSES, 'Situação', $errors),
            'stage' => $this->catalogFilter($query['stage'] ?? null, PipelineManager::STAGES, 'Estágio', $errors),
        ];
        $propertyId = $this->positiveInt($query['property_id'] ?? null);
        if (($query['property_id'] ?? '') !== '' && $propertyId === null) $errors[] = 'O imóvel selecionado foi ignorado por ser inválido.';
        $page = $this->page($query['page'] ?? null);
        [$typeCounts, $typeError] = $this->capture(fn() => $this->properties->leadCountsByType($filters), 'Imóveis: tipos');
        [$propertyList, $propertyError] = $this->capture(fn() => $this->properties->paginateProperties($filters, $page), 'Imóveis: ranking');
        [$selectedProperty, $selectedPropertyError] = $propertyId === null
            ? [null, null]
            : $this->capture(fn() => $this->properties->findProperty($propertyId), 'Imóveis: identificação');
        [$selectedLeads, $selectedLeadsError] = $propertyId === null
            ? [null, null]
            : $this->capture(fn() => $this->properties->paginatePropertyLeads($propertyId, $filters, $this->page($query['lead_page'] ?? null)), 'Imóveis: leads do imóvel');
        return [
            'filters' => ['date_from' => $dates['date_from'], 'date_to' => $dates['date_to'], 'type' => $filters['type'], 'status' => $filters['status'], 'stage' => $filters['stage'], 'property_id' => $propertyId],
            'errors' => array_values(array_filter(array_merge($errors, [$typeError, $propertyError, $selectedPropertyError, $selectedLeadsError]))),
            'panel_errors' => ['types' => $typeError, 'properties' => $propertyError, 'selected_property' => $selectedPropertyError, 'selected_property_leads' => $selectedLeadsError],
            'type_counts' => $typeCounts ?? [],
            'properties' => $propertyList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15, 'pages' => 1],
            'selected_property' => $selectedProperty,
            'selected_property_leads' => $selectedLeads,
        ];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function ownersDashboard(array $query): array
    {
        $errors = [];
        $filters = [
            'type' => $this->catalogFilter($query['type'] ?? null, PropertyService::TYPES, 'Tipo', $errors),
            'status' => $this->catalogFilter($query['status'] ?? null, PropertyService::STATUSES, 'Situação', $errors),
            'intention' => $this->catalogFilter($query['intention'] ?? null, PropertyService::INTENTIONS, 'Modalidade', $errors),
            'value_min' => $this->moneyFilter($query['value_min'] ?? null, 'Valor mínimo', $errors),
            'value_max' => $this->moneyFilter($query['value_max'] ?? null, 'Valor máximo', $errors),
        ];
        if ($filters['value_min'] !== null && $filters['value_max'] !== null && $filters['value_min'] > $filters['value_max']) {
            $errors[] = 'A faixa de valores foi ignorada porque o mínimo supera o máximo.';
            $filters['value_min'] = $filters['value_max'] = null;
        }
        [$countRanking, $countError] = $this->capture(fn() => $this->owners->paginateOwners('count', $filters, $this->page($query['count_page'] ?? null)), 'Proprietários: quantidade');
        [$saleRanking, $saleError] = $this->capture(fn() => $this->owners->paginateOwners('sale', $filters, $this->page($query['sale_page'] ?? null)), 'Proprietários: venda');
        [$rentRanking, $rentError] = $this->capture(fn() => $this->owners->paginateOwners('rent', $filters, $this->page($query['rent_page'] ?? null)), 'Proprietários: aluguel');
        [$contactTimestamp, $contactError] = $this->capture(fn() => $this->owners->latestConfirmedOwnerContactUtc(), 'Proprietários: contato');
        $rankingPages = [
            'count' => $countRanking ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15, 'pages' => 1],
            'sale' => $saleRanking ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15, 'pages' => 1],
            'rent' => $rentRanking ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15, 'pages' => 1],
        ];
        $ranking = $this->stringValue($query['ranking'] ?? null);
        $ownerId = $this->positiveInt($query['owner_id'] ?? null);
        $contributions = null;
        $contributionError = null;
        $ownerName = null;
        if ($ownerId !== null && in_array($ranking, ['count', 'sale', 'rent'], true)) {
            [$contributions, $contributionError] = $this->capture(fn() => $this->owners->paginateContributions($ownerId, $ranking, $filters, $this->page($query['contribution_page'] ?? null)), 'Proprietários: imóveis considerados');
            [$ownerName, $ownerNameError] = $this->capture(fn() => $this->owners->ownerName($ownerId), 'Proprietários: identificação');
            if ($ownerNameError !== null) $contributionError = $contributionError ?? $ownerNameError;
        } elseif (($query['owner_id'] ?? '') !== '' || ($query['ranking'] ?? '') !== '') {
            $errors[] = 'O recorte do proprietário foi ignorado por conter parâmetros inválidos.';
        }
        return [
            'filters' => $filters,
            'errors' => array_values(array_filter(array_merge($errors, [$countError, $saleError, $rentError, $contactError, $contributionError]))),
            'panel_errors' => ['count' => $countError, 'sale' => $saleError, 'rent' => $rentError, 'contact' => $contactError, 'contributions' => $contributionError],
            'rankings' => $rankingPages,
            'selected_owner' => $contributions === null ? null : ['owner_id' => $ownerId, 'ranking' => $ranking, 'name' => $ownerName],
            'contributions' => $contributions,
            'days_since_last_confirmed_contact' => $contactError === null ? $this->daysSinceLatestOwnerContact($contactTimestamp) : null,
        ];
    }

    /** Convert local inclusive calendar dates to UTC half-open bounds for SQLite CURRENT_TIMESTAMP values. */
    private function dateFilters(array $query): array
    {
        $errors = [];
        $fromDate = $this->validDate($query['date_from'] ?? null, 'Data inicial', $errors);
        $toDate = $this->validDate($query['date_to'] ?? null, 'Data final', $errors);
        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            $errors[] = 'O período foi ignorado porque a data inicial é posterior à data final.';
            $fromDate = $toDate = null;
        }
        $localZone = DateHelper::getTimezone();
        $utcZone = new DateTimeZone('UTC');
        $from = $fromDate === null ? null : (new DateTimeImmutable($fromDate . ' 00:00:00', $localZone))->setTimezone($utcZone)->format('Y-m-d H:i:s');
        $to = $toDate === null ? null : (new DateTimeImmutable($toDate . ' 00:00:00', $localZone))->modify('+1 day')->setTimezone($utcZone)->format('Y-m-d H:i:s');
        return [['date_from' => $fromDate, 'date_to' => $toDate, 'from' => $from, 'to' => $to], $errors];
    }

    private function validDate(mixed $value, string $label, array &$errors): ?string
    {
        $value = $this->stringValue($value);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, DateHelper::getTimezone());
        $issues = DateTimeImmutable::getLastErrors();
        if ($date === false || ($issues !== false && ($issues['warning_count'] > 0 || $issues['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            $errors[] = "{$label} inválida e ignorada.";
            return null;
        }
        return $value;
    }

    private function catalogFilter(mixed $raw, array $catalog, string $label, array &$errors): ?string
    {
        $value = $this->stringValue($raw);
        if ($value === '') return null;
        if (!array_key_exists($value, $catalog)) { $errors[] = "{$label} inválido e ignorado."; return null; }
        return $value;
    }

    private function moneyFilter(mixed $raw, string $label, array &$errors): ?float
    {
        if ($raw === null || $this->stringValue($raw) === '') return null;
        $value = $this->stringValue($raw);
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) { $errors[] = "{$label} inválido e ignorado."; return null; }
        return (float)$value;
    }

    private function page(mixed $raw): int
    {
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) return 1;
        return max(1, min(1000000, (int)$raw));
    }

    private function positiveInt(mixed $raw): ?int
    {
        if (filter_var($raw, FILTER_VALIDATE_INT) === false || (int)$raw < 1) return null;
        return (int)$raw;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string)$value) : '';
    }

    private function daysSinceLatestOwnerContact(?string $utcTimestamp): ?int
    {
        if ($utcTimestamp === null) return null;
        $local = (new DateTimeImmutable($utcTimestamp, new DateTimeZone('UTC')))->setTimezone(DateHelper::getTimezone())->setTime(0, 0);
        $today = DateHelper::now()->setTime(0, 0);
        return max(0, (int)$local->diff($today)->format('%r%a'));
    }

    /** @return array{mixed,?string} */
    private function capture(callable $query, string $label): array
    {
        try {
            return [$query(), null];
        } catch (\Throwable $exception) {
            error_log('Dashboard metric query failed [' . $label . ']: ' . $exception->getMessage());
            return [null, 'Não foi possível carregar este indicador.'];
        }
    }

}
