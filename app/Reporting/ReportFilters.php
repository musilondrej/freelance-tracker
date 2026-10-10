<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\PricingType;
use App\Enums\ReportRange;
use App\Support\Period;

/**
 * The Reports page filters, read from the page's filter form state (also the URL), with safe defaults.
 */
final readonly class ReportFilters
{
    public function __construct(
        public Period $period,
        public ?int $clientId = null,
        public ?int $projectId = null,
        public ?PricingType $pricingType = null,
    ) {}

    /** @param  array<string, mixed>|null  $filters */
    public static function from(?array $filters): self
    {
        $range = ReportRange::tryFrom((string) ($filters['range'] ?? '')) ?? ReportRange::ThisMonth;
        $id = fn (string $key): ?int => filled($filters[$key] ?? null) ? (int) $filters[$key] : null;

        return new self(
            period: $range->period(self::date($filters['from'] ?? null), self::date($filters['to'] ?? null)),
            clientId: $id('client'),
            projectId: $id('project'),
            pricingType: PricingType::tryFrom((string) ($filters['pricing'] ?? '')),
        );
    }

    /** @return array<string, CurrencyTotals> */
    public function totals(): array
    {
        return FinancialSummary::for($this->period, $this->clientId, $this->projectId, $this->pricingType);
    }

    /**
     * @param  'client_id'|'project_id'  $dimension
     * @return array<int, NamedTotals>
     */
    public function breakdown(string $dimension): array
    {
        return FinancialSummary::breakdown($dimension, $this->period, $this->clientId, $this->projectId, $this->pricingType);
    }

    /** @return array<int, RateLine> */
    public function rates(): array
    {
        return RateHistory::for($this->period, $this->clientId, $this->projectId);
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
