<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Filament\Actions\ArchiveActions;
use App\Filament\Tables\ArchivedFilter;
use App\Filament\Tables\ClientFilter;
use App\Models\Builders\ProjectBuilder;
use App\Models\Builders\TaskBuilder;
use App\Models\Project;
use App\Support\Duration;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (ProjectBuilder $query) => $query->with('client')->withBudgetUsage())
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('client.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable()
                    ->hiddenOn(RelationManager::class),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('tasks_count')
                    ->label(__('Open tasks'))
                    ->counts(['tasks' => fn (TaskBuilder $query) => $query->active()->open()])
                    ->formatStateUsing(fn (int $state): ?string => $state > 0 ? (string) $state : null)
                    ->alignEnd(),
                TextColumn::make('time_entries_sum_duration_seconds')
                    ->label(__('Tracked'))
                    ->sum('timeEntries', 'duration_seconds')
                    ->formatStateUsing(fn (?int $state): ?string => $state > 0 ? Duration::format($state) : null)
                    ->alignEnd(),
                TextColumn::make('budget')
                    ->label(__('Budget usage'))
                    ->state(fn (Project $record): ?string => ($percent = $record->budgetUsagePercent()) === null ? null : $percent.' %')
                    ->description(fn (Project $record): ?string => $record->budget === null ? null
                        : Money::format($record->budgetConsumed(), $record->client->currency).' / '.Money::format($record->budget, $record->client->currency))
                    ->badge()
                    ->color(fn (Project $record): string => $record->budgetStatus()?->getColor() ?? 'gray')
                    ->alignEnd(),
                TextColumn::make('due_on')
                    ->label(__('Deadline'))
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                ClientFilter::make()->hiddenOn(RelationManager::class),
                SelectFilter::make('status')->label(__('Status'))->options(ProjectStatus::class)->multiple(),
                ArchivedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ...ArchiveActions::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No projects'))
            ->emptyStateDescription(__('Projects group tasks of a client and can have their own rate and budget.'));
    }
}
