<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reports;

use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Reporting\CurrencyTotals;
use App\Reporting\ReportFilters;
use App\Support\Duration;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use RefreshesOnTimerChange;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $totals = array_values(ReportFilters::from($this->pageFilters)->totals());

        if ($totals === []) {
            return [Stat::make(__('Tracked time'), '0:00 h')->description(__('No data for the selected filters.'))->icon(Heroicon::OutlinedClock)];
        }

        $stats = self::timeStats($totals);
        $severalCurrencies = count($totals) > 1;

        foreach ($totals as $t) {
            array_push($stats, ...self::moneyStats($t, $severalCurrencies));
        }

        return $stats;
    }

    /**
     * Time adds up across currencies, money does not.
     *
     * @param  non-empty-list<CurrencyTotals>  $totals
     * @return list<Stat>
     */
    private static function timeStats(array $totals): array
    {
        $tracked = array_sum(array_map(fn (CurrencyTotals $t): int => $t->trackedSeconds(), $totals));
        $billable = array_sum(array_map(fn (CurrencyTotals $t): int => $t->billableSeconds, $totals));
        $nonBillable = array_sum(array_map(fn (CurrencyTotals $t): int => $t->nonBillableSeconds, $totals));

        return [
            Stat::make(__('Tracked time'), self::hours($tracked))->icon(Heroicon::OutlinedClock),
            Stat::make(__('Billable time'), self::hours($billable))->icon(Heroicon::OutlinedCurrencyDollar),
            Stat::make(__('Non-billable time'), self::hours($nonBillable))->icon(Heroicon::OutlinedNoSymbol),
        ];
    }

    /**
     * @return list<Stat>
     */
    private static function moneyStats(CurrencyTotals $t, bool $severalCurrencies): array
    {
        $label = fn (string $text): string => $severalCurrencies ? $text.' · '.$t->currency->value : $text;

        return [
            Stat::make($label(__('Work value')), $t->format($t->workValue))
                ->description(__('Hourly :hourly + fixed :fixed', ['hourly' => $t->format($t->hourlyValue), 'fixed' => $t->format($t->fixedValue)]))
                ->icon(Heroicon::OutlinedBriefcase),
            Stat::make($label(__('Average hourly rate')), self::rate($t, $t->averageHourlyRate()))
                ->description(__('Real rate :rate, non-billable time included', ['rate' => self::rate($t, $t->realHourlyRate())]))
                ->icon(Heroicon::OutlinedScale),
            Stat::make($label(__('Invoiced')), $t->format($t->billed))
                ->description(__('To invoice now: :amount', ['amount' => Money::format($t->unbilled, $t->currency)]))
                ->icon(Heroicon::OutlinedBanknotes),
        ];
    }

    private static function hours(int $seconds): string
    {
        return Duration::format($seconds).' h';
    }

    private static function rate(CurrencyTotals $t, ?int $rate): string
    {
        return $rate === null ? '—' : $t->format($rate).'/h';
    }
}
