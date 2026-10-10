<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Statement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class StatementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // One row of key facts; the total stands out, empty fields are not shown.
                Section::make()
                    ->columnSpanFull()
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('client.name')->label(__('Client'))
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->weight(FontWeight::Medium)
                            ->url(fn (Statement $record): string => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('sent_on')->label(__('Sent'))->date()->icon(Heroicon::OutlinedCalendar)
                            ->placeholder(__('Not sent yet')),
                        TextEntry::make('total')
                            ->label(__('Total (excl. VAT)'))
                            ->state(fn (Statement $record): int => $record->total())
                            ->money(fn (Statement $record) => $record->currency, divideBy: 100, decimalPlaces: 2)
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('note')->label(__('Note'))->columnSpanFull()
                            ->visible(fn (Statement $record): bool => filled($record->note)),
                    ]),
            ]);
    }
}
