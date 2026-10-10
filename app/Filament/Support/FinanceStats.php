<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Currency;
use App\Reporting\CurrencyTotals;
use App\Reporting\CzkTotal;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Stat cards shared by the dashboard and detail pages. One currency shows as is; several currencies
 * show one CZK figure with the original amounts underneath; the rate date is in the page footer.
 */
final class FinanceStats
{
    /**
     * @param  non-empty-list<CurrencyTotals>  $totals
     * @param  string  $period  Lowercase period of work value and invoiced, like "this month". To invoice is always as of now.
     * @return array{workValue: Stat, toInvoice: Stat, invoiced: Stat}
     */
    public static function for(array $totals, string $period): array
    {
        return [
            'workValue' => self::stat(__('Work value :period', ['period' => $period]), $totals, fn (CurrencyTotals $t): int => $t->workValue)
                ->icon(Heroicon::OutlinedBriefcase),
            'toInvoice' => self::stat(__('To invoice'), $totals, fn (CurrencyTotals $t): int => $t->unbilled)
                ->icon(Heroicon::OutlinedDocumentPlus),
            'invoiced' => self::stat(__('Invoiced :period', ['period' => $period]), $totals, fn (CurrencyTotals $t): int => $t->billed)
                ->icon(Heroicon::OutlinedBanknotes),
        ];
    }

    /**
     * One currency as is. Several currencies as one CZK figure with the original amounts
     * underneath, or one line each before the first ČNB rate.
     *
     * @param  non-empty-list<CurrencyTotals>  $totals
     * @param  callable(CurrencyTotals): int  $figure
     */
    private static function stat(string $label, array $totals, callable $figure): Stat
    {
        /** @var Collection<string, int> $amounts currency code => amount */
        $amounts = collect($totals)
            ->mapWithKeys(fn (CurrencyTotals $t): array => [$t->currency->value => $figure($t)])
            ->reject(fn (int $amount): bool => $amount === 0);

        if ($amounts->isEmpty()) {
            return Stat::make($label, Money::format(0, $totals[0]->currency));
        }

        $formatted = $amounts->map(fn (int $amount, string $code): string => Money::format($amount, Currency::from($code)));

        if ($amounts->count() === 1) {
            return Stat::make($label, (string) $formatted->first());
        }

        $inCzk = CzkTotal::of($amounts->all());

        if (! $inCzk instanceof CzkTotal) {
            return Stat::make($label, new HtmlString($formatted->map(fn (string $line): string => e($line))->implode('<br>')));
        }

        return Stat::make($label, Money::format($inCzk->amount, Currency::CZK))
            ->description($formatted->implode(' + '));
    }
}
