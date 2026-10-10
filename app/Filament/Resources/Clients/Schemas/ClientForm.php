<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\Currency;
use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\WorkFields;
use App\Models\Client;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ClientForm
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
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->autofocus()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create' && blank($get('code')) && filled($state)) {
                                    $set('code', Client::suggestCode($state));
                                }
                            }),
                        TextInput::make('code')
                            ->label(__('Task key prefix'))
                            ->required()
                            ->maxLength(10)
                            ->regex(Client::CODE_PATTERN)
                            ->validationMessages(['regex' => __('2–10 letters or digits, starting with a letter.')])
                            ->unique()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', strtoupper((string) $state)))
                            ->helperText(__('Tasks get keys like ACME-12. Changing it changes the keys of all the client\'s tasks.')),
                        TextInput::make('company_name')->label(__('Company name'))->maxLength(255),
                        TextInput::make('email')->label(__('E-mail'))->email()->maxLength(255),
                        TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(50),
                        TextInput::make('website')->label(__('Website'))->url()->maxLength(255),
                    ]),
                Section::make(__('Rate'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('currency')
                            ->label(__('Currency'))
                            ->options(Currency::class)
                            ->default(fn (): Currency => Filament::auth()->user()->default_currency ?? Currency::CZK)
                            ->required()
                            ->live()
                            ->disabled(fn (?Client $record): bool => $record?->hasFinancialHistory() ?? false)
                            ->helperText(fn (?Client $record) => ($record?->hasFinancialHistory() ?? false)
                                ? __('Locked: the client already has time entries or statements.')
                                : __('All projects, tasks and amounts of the client use this currency.')),
                        MoneyInput::make('hourly_rate', positive: true)
                            ->label(__('Default hourly rate'))
                            ->default(fn (): ?int => Filament::auth()->user()?->default_hourly_rate)
                            ->required()
                            ->suffix(fn (Get $get): string => WorkFields::rateSuffix($get('currency'))),
                    ]),
                Section::make(__('Billing details'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('registration_number')->label(__('Company ID (IČO)'))->maxLength(20),
                        TextInput::make('vat_number')->label(__('VAT ID (DIČ)'))->maxLength(20),
                        TextInput::make('street')->label(__('Street'))->maxLength(255)->columnSpanFull(),
                        TextInput::make('city')->label(__('City'))->maxLength(255),
                        TextInput::make('postal_code')->label(__('Postal code'))->maxLength(20),
                        TextInput::make('country_code')->label(__('Country'))->default('CZ')->required()->length(2)
                            ->dehydrateStateUsing(fn (string $state): string => strtoupper($state)),
                    ]),
                Section::make(__('Other'))
                    ->columnSpan(1)
                    ->schema([
                        Textarea::make('notes')->label(__('Internal information'))->rows(5),
                    ]),
            ]);
    }
}
