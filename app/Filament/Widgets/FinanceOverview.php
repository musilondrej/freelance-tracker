<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Unbilled;
use App\Filament\Support\FinanceStats;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Reporting\FinancialSummary;
use App\Support\Period;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverview extends StatsOverviewWidget
{
    use RefreshesOnTimerChange;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    public bool $onlyUnbilled = false;

    protected function getStats(): array
    {
        $totals = array_values(FinancialSummary::for(Period::thisMonth()));

        if ($totals === []) {
            return [Stat::make(__('Work value'), '—')->description(__('Nothing tracked this month yet'))->icon(Heroicon::OutlinedBriefcase)];
        }

        $stats = FinanceStats::for($totals, __('this month'));

        if ($this->onlyUnbilled) {
            return [$stats['toInvoice']];
        }

        $stats['toInvoice']->url(Unbilled::getUrl());

        return array_values($stats);
    }
}
