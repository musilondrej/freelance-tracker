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
     * @param  string  $period  Lowercase period of work value and invoiced, like "tento měsíc". To invoice is always as of now.
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
     * @param  non-empty-list<CurrencyTotals>  $totals
     * @param  callable(CurrencyTotals): int  $figure
     */
    private static function stat(string $label, array $totals, callable $figure): Stat
    {
        /** @var Collection<int, CurrencyTotals> $nonZero */
        $nonZero = collect($totals)->reject(fn (CurrencyTotals $t): bool => $figure($t) === 0)->values();
        $formatted = $nonZero->map(fn (CurrencyTotals $t): string => Money::format($figure($t), $t->currency));
        $converted = $nonZero->count() > 1
            ? CzkTotal::of($nonZero->mapWithKeys(fn (CurrencyTotals $t): array => [$t->currency->value => $figure($t)])->all())
            : null;

        $value = match (true) {
            $converted instanceof CzkTotal => Money::format($converted->amount, Currency::CZK),
            // Without a rate yet, each currency gets its own line.
            $nonZero->count() > 1 => new HtmlString($formatted->map(fn (string $line): string => e($line))->implode('<br>')),
            default => $formatted->first() ?? Money::format(0, $totals[0]->currency),
        };

        // The converted total says right under it what it is made of; the rate date is in the page footer.
        $description = $converted instanceof CzkTotal ? $formatted->implode(' + ') : null;

        return Stat::make($label, $value)->description($description);
    }
}
