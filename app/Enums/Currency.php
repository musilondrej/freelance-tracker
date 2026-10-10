<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * ISO 4217 codes. The database accepts any 3-letter code, so a new currency only needs a new case.
 * All currencies here have 2 decimal places.
 */
enum Currency: string implements HasLabel
{
    case CZK = 'CZK';
    case EUR = 'EUR';

    public function getLabel(): string
    {
        return $this->value;
    }

    /** Fixed series colour in charts, so a currency keeps its colour whatever else is shown. */
    public function chartColor(): string
    {
        return match ($this) {
            self::CZK => '#2a78d6',
            self::EUR => '#eb6834',
        };
    }
}
