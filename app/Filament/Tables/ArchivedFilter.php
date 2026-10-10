<?php

declare(strict_types=1);

namespace App\Filament\Tables;

use Filament\Tables\Filters\TernaryFilter;

final class ArchivedFilter
{
    public static function make(): TernaryFilter
    {
        return TernaryFilter::make('archived')
            ->label(__('Archived'))
            ->nullable()
            ->attribute('archived_at')
            ->placeholder(__('All'))
            ->trueLabel(__('Archived only'))
            ->falseLabel(__('Active only'))
            ->default(false);
    }
}
