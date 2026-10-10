<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Unbilled;
use App\Filament\Support\FinanceStats;
use App\Reporting\CurrencyTotals;
use App\Reporting\FinancialSummary;
use App\Support\Period;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * This month's money. Several currencies show one approximate CZK figure, see FinanceStats.
 */
class FinanceOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    public bool $onlyUnbilled = false;

    /** @var list<CurrencyTotals>|null */
    private ?array $totals = null;

    protected function getStats(): array
    {
        $totals = $this->totals();

        if ($totals === []) {
            return [Stat::make(__('Work value'), '—')->description(__('Nothing tracked this month yet'))->icon(Heroicon::OutlinedBriefcase)];
        }

        $stats = FinanceStats::for($totals, __('this month'));

        if ($this->onlyUnbilled) {
            return [$stats['toInvoice']];
        }

        // The most actionable number: one click to the list it comes from.
        $stats['toInvoice']->url(Unbilled::getUrl());

        return array_values($stats);
    }

    /** @return list<CurrencyTotals> */
    private function totals(): array
    {
        return $this->totals ??= array_values(FinancialSummary::for(Period::thisMonth()));
    }
}
