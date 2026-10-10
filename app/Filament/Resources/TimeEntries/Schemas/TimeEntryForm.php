<?php

declare(strict_types=1);

namespace App\Filament\Resources\TimeEntries\Schemas;

use App\Filament\Forms\DurationInput;
use App\Models\Builders\TaskBuilder;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class TimeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('task_id')
                    ->label(__('Task'))
                    ->relationship('task', 'title', fn (TaskBuilder $query) => $query->active()->with(['client', 'project']))
                    ->getOptionLabelFromRecordUsing(fn (Task $record): string => $record->optionLabel())
                    ->searchable(['title'])
                    ->preload()
                    ->required()
                    ->columnSpanFull()
                    ->hidden(fn (mixed $livewire): bool => $livewire instanceof RelationManager),
                DateTimePicker::make('started_at')
                    ->label(__('Start'))
                    ->seconds(false)
                    ->default(fn () => now()->subHour()->startOfMinute())
                    ->required(),
                DateTimePicker::make('ended_at')
                    ->label(__('End'))
                    ->seconds(false)
                    ->after('started_at')
                    ->requiredWithout('duration')
                    ->helperText(__('Or enter the duration instead.')),
                DurationInput::make('duration')
                    ->label(__('Duration'))
                    ->helperText(__('Overrides the end. E.g. 1:30, 90m, 2h 15m or 1,5')),
                Textarea::make('note')->label(__('Note'))->rows(2)->columnSpanFull(),
            ]);
    }

    /**
     * Turns form data into model attributes. A duration in seconds sets ended_at from started_at.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepare(array $data): array
    {
        if (is_int($data['duration'] ?? null) && filled($data['started_at'] ?? null)) {
            $data['ended_at'] = CarbonImmutable::parse((string) $data['started_at'])->addSeconds($data['duration']);
        }

        unset($data['duration']);

        return $data;
    }
}
