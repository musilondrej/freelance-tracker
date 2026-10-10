<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Str;

/**
 * Sentence case for labels: Czech capitalises only the first word, Filament capitalises every word.
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
