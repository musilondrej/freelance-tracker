<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

/** Budget state of a project. Computed by App\Reporting\ProjectStats::status(). */
enum BudgetStatus: string implements HasColor
{
    case Ok = 'ok';
    case Near = 'near';
    case Over = 'over';

    public function getColor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Near => 'warning',
            self::Over => 'danger',
        };
    }
}
