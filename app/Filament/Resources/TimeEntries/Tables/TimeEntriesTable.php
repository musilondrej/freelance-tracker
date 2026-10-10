<?php

declare(strict_types=1);

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Enums\PricingType;
use App\Filament\Actions\StatementActions;
use App\Filament\Resources\Tasks\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Models\Builders\TimeEntryBuilder;
use App\Models\TimeEntry;
use App\Support\Duration;
use App\Support\Money;
use App\Support\Period;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TimeEntriesTable
{
    public static function configure(Table $table): Table
    {
        $inTask = fn (mixed $livewire): bool => $livewire instanceof TimeEntriesRelationManager;
        // Entries on a statement cannot change. A running entry is stopped only by the timer.
        $readOnly = fn (TimeEntry $record): bool => $record->isRunning() || (bool) $record->getAttribute('is_allocated');

        return $table
            ->modifyQueryUsing(fn (TimeEntryBuilder $query) => $query
                ->with(['task.client', 'task.project'])
                ->withAllocation())
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')
                    ->label(__('Date'))
                    ->date()
                    ->description(fn (TimeEntry $record): string => self::timeRange($record))
                    ->sortable(),
                TextColumn::make('task.title')
                    ->label(__('Task'))
                    ->description(fn (TimeEntry $record): string => $record->task->contextLabel())
                    ->url(fn (TimeEntry $record): string => TaskResource::getUrl('view', ['record' => $record->task_id]))
                    ->searchable()
                    ->wrap()
                    ->hidden($inTask),
                TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(60)
                    ->wrap()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration_seconds')
                    ->label(__('Duration'))
                    ->formatStateUsing(fn (?int $state): string => Duration::format($state))
                    ->placeholder(__('Running'))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->formatStateUsing(fn (mixed $state): string => Duration::format((int) $state))),
                TextColumn::make('hourly_rate')
                    ->label(__('Rate'))
                    ->formatStateUsing(fn (?int $state, TimeEntry $record): ?string => $state === null || $record->currency === null ? null : Money::format($state, $record->currency))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label(__('Value'))
                    ->state(fn (TimeEntry $record): ?string => match (true) {
                        $record->isRunning() || $record->currency === null => null,
                        $record->pricing_type === PricingType::Hourly => Money::format((int) $record->amount, $record->currency),
                        default => $record->pricing_type?->getLabel(),
                    })
                    ->color(fn (TimeEntry $record): ?string => $record->pricing_type === PricingType::Hourly ? null : 'gray')
                    ->description(fn (TimeEntry $record): ?string => $record->getAttribute('is_allocated') ? (string) __('On statement') : null)
                    ->alignEnd(),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('Until')),
                    ])
                    ->columns(2)
                    ->query(function (TimeEntryBuilder $query, array $data): void {
                        // Each bound is a whole local day; a missing bound is open.
                        $query
                            ->when(filled($data['from'] ?? null), fn (TimeEntryBuilder $q) => $q->where('started_at', '>=', Period::day((string) $data['from'])->start))
                            ->when(filled($data['until'] ?? null), fn (TimeEntryBuilder $q) => $q->where('started_at', '<', Period::day((string) $data['until'])->end));
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['from'] ?? null) || filled($data['until'] ?? null)
                        ? __('Period').': '.($data['from'] ?? '…').' – '.($data['until'] ?? '…')
                        : null),
                SelectFilter::make('client')
                    ->label(__('Client'))
                    ->relationship('task.client', 'name')
                    ->searchable()
                    ->preload()
                    ->hidden($inTask),
                SelectFilter::make('project')
                    ->label(__('Project'))
                    ->relationship('task.project', 'name')
                    ->searchable()
                    ->preload()
                    ->hidden($inTask),
                SelectFilter::make('task')
                    ->label(__('Task'))
                    ->relationship('task', 'title')
                    ->searchable()
                    ->hidden($inTask),
                SelectFilter::make('pricing_type')->label(__('Pricing'))->options(PricingType::class),
                TernaryFilter::make('billed')
                    ->label(__('On statement'))
                    ->queries(
                        true: fn (TimeEntryBuilder $query) => $query->allocated(),
                        false: fn (TimeEntryBuilder $query) => $query->unallocated(),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->mutateDataUsing(fn (array $data): array => TimeEntryForm::prepare($data))
                        ->hidden($readOnly),
                    DeleteAction::make()
                        ->hidden($readOnly),
                ]),
            ])
            ->toolbarActions([
                StatementActions::addTimeEntriesToStatement(),
            ])
            ->emptyStateHeading(__('No time entries'))
            ->emptyStateDescription(__('Start a timer on a task or log time manually.'));
    }

    private static function timeRange(TimeEntry $entry): string
    {
        $tz = Period::timezone();

        return $entry->started_at->setTimezone($tz)->format('H:i').'–'.($entry->ended_at?->setTimezone($tz)->format('H:i') ?? '…');
    }
}
