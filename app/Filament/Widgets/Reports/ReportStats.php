<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reports;

use App\Reporting\CurrencyTotals;
use App\Reporting\ReportFilters;
use App\Support\Duration;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Totals of the Reports filters: time first (it adds up across currencies), then money per currency.
 */
class ReportStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $totals = array_values(ReportFilters::from($this->pageFilters)->totals());

        if ($totals === []) {
            return [Stat::make(__('Tracked time'), '0:00 h')->description(__('No data for the selected filters.'))->icon(Heroicon::OutlinedClock)];
        }

        $sum = fn (callable $figure): int => array_sum(array_map($figure, $totals));
        $hours = fn (int $seconds): string => Duration::format($seconds).' h';

        $stats = [
            Stat::make(__('Tracked time'), $hours($sum(fn (CurrencyTotals $t): int => $t->trackedSeconds())))->icon(Heroicon::OutlinedClock),
            Stat::make(__('Billable time'), $hours($sum(fn (CurrencyTotals $t): int => $t->billableSeconds)))->icon(Heroicon::OutlinedCurrencyDollar),
            Stat::make(__('Non-billable time'), $hours($sum(fn (CurrencyTotals $t): int => $t->nonBillableSeconds)))->icon(Heroicon::OutlinedNoSymbol),
        ];

        foreach ($totals as $t) {
            // The currency in the label only when the report has more than one.
            $label = fn (string $text): string => count($totals) > 1 ? $text.' · '.$t->currency->value : $text;
            $rate = fn (?int $rate): string => $rate === null ? '—' : $t->format($rate).'/h';

            $stats[] = Stat::make($label(__('Work value')), $t->format($t->workValue))
                ->description(__('Hourly :hourly + fixed :fixed', ['hourly' => $t->format($t->hourlyValue), 'fixed' => $t->format($t->fixedValue)]))
                ->icon(Heroicon::OutlinedBriefcase);
            $stats[] = Stat::make($label(__('Average hourly rate')), $rate($t->averageHourlyRate()))
                ->description(__('Real rate :rate, non-billable time included', ['rate' => $rate($t->realHourlyRate())]))
                ->icon(Heroicon::OutlinedScale);
            $stats[] = Stat::make($label(__('Invoiced')), $t->format($t->billed))
                ->description(__('To invoice now: :amount', ['amount' => Money::format($t->unbilled, $t->currency)]))
                ->icon(Heroicon::OutlinedBanknotes);
        }

        return $stats;
    }
}
