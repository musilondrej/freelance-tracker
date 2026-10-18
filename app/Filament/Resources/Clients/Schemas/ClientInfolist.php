<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Contact'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('company_name')->label(__('Company name'))->visible(fn (mixed $state): bool => filled($state)),
                        TextEntry::make('code')->label(__('Task key prefix'))->fontFamily('mono'),
                        TextEntry::make('email')->label(__('E-mail'))->copyable()->visible(fn (mixed $state): bool => filled($state)),
                        TextEntry::make('phone')->label(__('Phone'))->visible(fn (mixed $state): bool => filled($state)),
                        TextEntry::make('website')->label(__('Website'))->url(fn (Client $record): ?string => $record->website, shouldOpenInNewTab: true)->visible(fn (mixed $state): bool => filled($state)),
                        TextEntry::make('address')
                            ->label(__('Billing address'))
                            ->state(fn (Client $record): string => $record->address())
                            ->columnSpanFull(),
                        TextEntry::make('registration_number')->label(__('Company ID (IČO)'))->visible(fn (mixed $state): bool => filled($state)),
                        TextEntry::make('vat_number')->label(__('VAT ID (DIČ)'))->visible(fn (mixed $state): bool => filled($state)),
                    ]),
                Section::make(__('Rate'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('hourly_rate')->label(__('Default hourly rate'))->formatStateUsing(fn (int $state, Client $record): string => Money::format($state, $record->currency)),
                        TextEntry::make('currency')->label(__('Currency'))->badge(),
                        TextEntry::make('archived_at')->label(__('Archived'))->dateTime()->visible(fn (Client $record): bool => $record->isArchived()),
                    ]),
                Section::make(__('Internal information'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Client $record): bool => filled($record->notes))
                    ->schema([
                        TextEntry::make('notes')->hiddenLabel(),
                    ]),
            ]);
    }
}
