<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Period;
use Filament\Support\Contracts\HasLabel;

enum ReportRange: string implements HasLabel
{
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::ThisMonth => __('This month'),
            self::LastMonth => __('Last month'),
            self::ThisYear => __('This year'),
            self::LastYear => __('Last year'),
            self::Custom => __('Custom'),
        };
    }

    /** Local calendar period. Invalid custom dates, for example from the URL, fall back to this month. */
    public function period(?string $from = null, ?string $to = null): Period
    {
        $today = Period::localToday();
        $lastMonth = $today->subMonthNoOverflow();

        return match ($this) {
            self::ThisMonth => Period::thisMonth(),
            self::LastMonth => Period::between($lastMonth->startOfMonth()->toDateString(), $lastMonth->endOfMonth()->toDateString()),
            self::ThisYear => Period::between($today->startOfYear()->toDateString(), $today->endOfYear()->toDateString()),
            self::LastYear => Period::between($today->subYear()->startOfYear()->toDateString(), $today->subYear()->endOfYear()->toDateString()),
            self::Custom => rescue(
                fn (): Period => Period::between($from ?: $today->startOfMonth()->toDateString(), $to ?: $today->toDateString()),
                fn (): Period => Period::thisMonth(),
                report: false,
            ),
        };
    }
}
