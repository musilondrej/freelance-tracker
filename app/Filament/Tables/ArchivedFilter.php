<?php

declare(strict_types=1);

namespace App\Filament\Tables;

use App\Models\Builders\ArchivableBuilder;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Filters\Filter;

final class ArchivedFilter
{
    /** Archived records are hidden until the switch is on. Filament skips the query of an inactive toggle filter, hence the own toggle. */
    public static function make(): Filter
    {
        return Filter::make('archived')
            ->schema([Toggle::make('show')->label(__('Show archived'))])
            ->query(fn (ArchivableBuilder $query, array $data) => $query->unless($data['show'] ?? false, fn (ArchivableBuilder $q) => $q->active()))
            ->indicateUsing(fn (array $data): ?string => ($data['show'] ?? false) ? (string) __('Show archived') : null);
    }
}
