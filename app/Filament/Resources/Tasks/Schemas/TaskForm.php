<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\PricingType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Forms\DurationInput;
use App\Filament\Forms\MarkdownField;
use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\WorkFields;
use App\Models\Task;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class TaskForm
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
                        TextInput::make('title')->label(__('Title'))->required()->maxLength(255)->autofocus()->columnSpanFull(),
                        ...WorkFields::clientAndProject(
                            fn (?Model $record): bool => $record instanceof Task && $record->isPlacementLocked(),
                            __('Locked: the task already has time entries.'),
                        ),
                        MarkdownField::make('description')->label(__('Description'))->columnSpanFull(),
                    ]),
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')->label(__('Status'))->options(TaskStatus::class)
                            ->default(fn (): TaskStatus => TaskStatus::tryFrom(request()->string('status')->toString()) ?? TaskStatus::Todo)
                            ->required(),
                        Select::make('priority')->label(__('Priority'))->options(TaskPriority::class)->default(TaskPriority::Normal)->required(),
                        DatePicker::make('due_on')->label(__('Deadline')),
                        DurationInput::make('estimated_seconds')->label(__('Estimated time')),
                    ]),
                Section::make(__('Billing'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('pricing_type')
                            ->label(__('Pricing'))
                            ->options(PricingType::class)
                            ->default(PricingType::Hourly)
                            ->inline()
                            ->required()
                            ->live()
                            ->disabled(fn (?Task $record): bool => $record?->isPricingLocked() ?? false)
                            ->helperText(fn (?Task $record) => ($record?->isPricingLocked() ?? false) ? __('Locked: the task is ready to bill or billed.') : null)
                            ->columnSpanFull(),
                        WorkFields::hourlyRate()
                            ->label(__('Own hourly rate'))
                            ->visible(fn (Get $get): bool => WorkFields::pricing($get) === PricingType::Hourly)
                            ->helperText(__('Empty = project or client rate. Changing it does not affect existing entries.')),
                        MoneyInput::make('fixed_price', positive: true)
                            ->label(__('Fixed price'))
                            ->visible(fn (Get $get): bool => WorkFields::pricing($get) === PricingType::Fixed)
                            ->required()
                            ->suffix(fn (Get $get): string => WorkFields::currency($get))
                            ->disabled(fn (?Task $record): bool => $record?->isReadyToBill() ?? false)
                            ->helperText(fn (?Task $record) => ($record?->isReadyToBill() ?? false) ? __('Locked while the task is ready to bill.') : null),
                    ]),
            ]);
    }
}
