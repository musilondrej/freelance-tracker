<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Filament\Forms\DurationInput;
use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\WorkFields;
use App\Models\Builders\ArchivableBuilder;
use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Project'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->autofocus()->columnSpanFull(),
                        Select::make('client_id')
                            ->label(__('Client'))
                            ->relationship('client', 'name', fn (ArchivableBuilder $query) => $query->active())
                            ->default(fn (): ?int => request()->integer('client') ?: null)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (?Project $record): bool => $record?->tasks()->exists() ?? false)
                            ->helperText(fn (?Project $record) => ($record?->tasks()->exists() ?? false) ? __('Locked: the project already has tasks.') : null),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(ProjectStatus::class)
                            ->default(ProjectStatus::Active)
                            ->required(),
                        Textarea::make('description')->label(__('Description'))->rows(4)->columnSpanFull(),
                    ]),
                Section::make(__('Budget and rate'))
                    ->columnSpan(1)
                    ->schema([
                        MoneyInput::make('hourly_rate', positive: true)
                            ->label(__('Hourly rate'))
                            ->suffix(fn (Get $get): string => WorkFields::currency($get).'/h')
                            ->placeholder(fn (Get $get): ?string => WorkFields::inheritedRate($get))
                            ->helperText(__('Empty = client rate.')),
                        MoneyInput::make('budget')
                            ->label(__('Budget'))
                            ->suffix(fn (Get $get): string => WorkFields::currency($get)),
                        DurationInput::make('estimated_seconds')->label(__('Estimated time')),
                    ]),
                Section::make(__('Schedule'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        DatePicker::make('starts_on')->label(__('Start')),
                        DatePicker::make('due_on')->label(__('Deadline'))->afterOrEqual('starts_on'),
                    ]),
                Section::make(__('Internal information'))
                    ->columnSpan(1)
                    ->schema([
                        Textarea::make('notes')->hiddenLabel()->rows(4),
                    ]),
            ]);
    }
}
