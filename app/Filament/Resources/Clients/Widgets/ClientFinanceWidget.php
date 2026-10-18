<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Widgets;

use App\Filament\Support\FinanceStats;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\Client;
use App\Reporting\CurrencyTotals;
use App\Reporting\FinancialSummary;
use App\Support\Period;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientFinanceWidget extends StatsOverviewWidget
{
    use RefreshesOnTimerChange;

    public ?Client $record = null;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        assert($this->record instanceof Client);

        $currency = $this->record->currency;
        $totals = FinancialSummary::for(Period::allTime(), clientId: $this->record->id)[$currency->value] ?? new CurrencyTotals($currency);

        $rate = $totals->realHourlyRate();

        return [
            ...array_values(FinanceStats::for([$totals], __('in total'))),
            Stat::make(__('Real hourly rate'), $rate === null ? '—' : $totals->format($rate).'/h')
                ->description(__('Work value per tracked hour, non-billable time included'))
                ->icon(Heroicon::OutlinedScale),
        ];
    }
}
