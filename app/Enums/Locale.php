<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** UI language. English is the source language of `__()`, Czech comes from lang/cs.json. */
enum Locale: string implements HasLabel
{
    case Czech = 'cs';
    case English = 'en';

    // Native names, so the switch is readable in either language.
    public function getLabel(): string
    {
        return match ($this) {
            self::Czech => 'Čeština',
            self::English => 'English',
        };
    }
}
