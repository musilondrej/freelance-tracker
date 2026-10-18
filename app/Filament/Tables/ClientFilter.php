<?php

declare(strict_types=1);

namespace App\Filament\Tables;

use Filament\Tables\Filters\SelectFilter;

final class ClientFilter
{
    public static function make(string $relationship = 'client'): SelectFilter
    {
        return SelectFilter::make('client')
            ->label(__('Client'))
            ->relationship($relationship, 'name')
            ->searchable()
            ->preload();
    }
}
