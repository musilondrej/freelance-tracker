<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTasks\Schemas;

use App\Enums\BillingTiming;
use App\Enums\PricingType;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Filament\Forms\DurationInput;
use App\Filament\Forms\MarkdownField;
use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\WorkFields;
use App\Models\RecurringTask;
use App\Support\Period;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RecurringTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        $scheduleLocked = fn (?Model $record): bool => $record instanceof RecurringTask && $record->isScheduleLocked();

        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(200)
                            ->autofocus()
                            ->helperText(__('The period is appended to each generated task, e.g. "– October 2026".'))
                            ->columnSpanFull(),
                        ...WorkFields::clientAndProject($scheduleLocked, __('Locked: tasks were already generated.')),
                        MarkdownField::make('description')->label(__('Description'))->columnSpanFull(),
                    ]),
                Section::make(__('Schedule'))
                    ->columnSpan(1)
                    ->schema([
                        ToggleButtons::make('frequency')
                            ->label(__('Repeats'))
                            ->options(RecurrenceFrequency::class)
                            ->default(RecurrenceFrequency::Monthly)
                            ->inline()
                            ->required()
                            ->disabled($scheduleLocked),
                        TextInput::make('interval')
                            ->label(__('Every'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(24)
                            ->default(1)
                            ->required()
                            ->helperText(__('E.g. 2 with monthly = every two months.'))
                            ->disabled($scheduleLocked),
                        DatePicker::make('starts_on')
                            ->label(__('First period starts'))
                            ->default(fn (): string => Period::localToday()->startOfMonth()->toDateString())
                            ->required()
                            ->disabled($scheduleLocked),
                        DatePicker::make('ends_on')
                            ->label(__('Ends'))
                            ->afterOrEqual('starts_on')
                            ->helperText(__('Empty = until archived.')),
                    ]),
                Section::make(__('Billing'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('pricing_type')
                            ->label(__('Pricing'))
                            ->options(PricingType::class)
                            ->default(PricingType::Fixed)
                            ->inline()
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        MoneyInput::make('fixed_price', positive: true)
                            ->label(__('Price per period'))
                            ->visible(fn (Get $get): bool => WorkFields::pricing($get) === PricingType::Fixed)
                            ->required()
                            ->suffix(fn (Get $get): string => WorkFields::currency($get)),
                        ToggleButtons::make('billing_timing')
                            ->label(__('Ready to bill'))
                            ->options(BillingTiming::class)
                            ->default(BillingTiming::InArrears)
                            ->visible(fn (Get $get): bool => WorkFields::pricing($get) === PricingType::Fixed)
                            ->required(),
                        WorkFields::hourlyRate()
                            ->label(__('Own hourly rate'))
                            ->visible(fn (Get $get): bool => WorkFields::pricing($get) === PricingType::Hourly)
                            ->helperText(__('Empty = project or client rate.')),
                    ]),
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        Select::make('priority')->label(__('Priority'))->options(TaskPriority::class)->default(TaskPriority::Normal)->required(),
                        DurationInput::make('estimated_seconds')->label(__('Estimated time per period')),
                    ]),
            ]);
    }
}
