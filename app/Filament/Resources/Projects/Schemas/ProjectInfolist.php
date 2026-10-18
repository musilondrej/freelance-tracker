<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Project;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.name')
                            ->label(__('Client'))
                            ->url(fn (Project $record): string => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('description')->label(__('Description'))->visible(fn (mixed $state): bool => filled($state))->columnSpanFull(),
                    ]),
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('hourly_rate')
                            ->label(__('Hourly rate'))
                            ->state(fn (Project $record): int => $record->resolveHourlyRate())
                            ->formatStateUsing(fn (int $state, Project $record): string => Money::format($state, $record->client->currency))
                            ->helperText(fn (Project $record) => $record->hourly_rate === null ? __('Inherited from the client') : null),
                        TextEntry::make('starts_on')->label(__('Start'))->date()->visible(fn (mixed $state): bool => filled($state)),
                        TextEntry::make('due_on')->label(__('Deadline'))->date()->visible(fn (mixed $state): bool => filled($state)),
                    ]),
                Section::make(__('Internal information'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Project $record): bool => filled($record->notes))
                    ->schema([TextEntry::make('notes')->hiddenLabel()]),
            ]);
    }
}
