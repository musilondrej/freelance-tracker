<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** When the fixed price of a recurring period becomes billable. */
enum BillingTiming: string implements HasLabel
{
    /** At the start of the period, like yearly hosting. */
    case InAdvance = 'in_advance';

    /** After the period ends, like monthly maintenance. */
    case InArrears = 'in_arrears';

    public function getLabel(): string
    {
        return match ($this) {
            self::InAdvance => __('In advance (period start)'),
            self::InArrears => __('In arrears (after the period)'),
        };
    }
}
