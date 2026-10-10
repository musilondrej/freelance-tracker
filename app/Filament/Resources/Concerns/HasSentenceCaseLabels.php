<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Str;

/**
 * Czech capitalises only the first word: "Výkazy práce", not Filament's "Výkazy Práce".
 */
trait HasSentenceCaseLabels
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
