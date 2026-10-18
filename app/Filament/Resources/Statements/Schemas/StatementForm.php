<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Schemas;

use App\Filament\Forms\WorkFields;
use App\Models\Statement;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Only free-text fields are edited here. Status, dates and items change through billing actions.
 */
class StatementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    WorkFields::client()
                        ->visibleOn('create')
                        ->helperText(__('The statement uses the client\'s currency.')),
                    Textarea::make('note')->label(__('Note'))->rows(3)->columnSpanFull(),
                ])
                ->disabled(fn (?Statement $record): bool => $record instanceof Statement && ! $record->isDraft()),
        ]);
    }
}
