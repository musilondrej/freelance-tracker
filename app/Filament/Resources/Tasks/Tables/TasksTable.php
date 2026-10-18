<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\PricingType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Actions\ArchiveActions;
use App\Filament\Actions\TaskActions;
use App\Filament\Resources\Clients\RelationManagers\TasksRelationManager as ClientTasks;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager as ProjectTasks;
use App\Filament\Tables\ArchivedFilter;
use App\Filament\Tables\ClientFilter;
use App\Models\Builders\TaskBuilder;
use App\Models\Task;
use App\Support\Duration;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (TaskBuilder $query) => $query
                ->with(['client', 'project'])
                ->withTracking()
                ->withChecklistProgress())
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('Key'))
                    ->state(fn (Task $record): string => $record->key())
                    ->searchable(query: fn (TaskBuilder $query, string $search) => $query->withKey($search))
                    ->copyable()
                    ->fontFamily('mono')
                    ->color('gray'),
                TextColumn::make('title')
                    ->label(__('Task'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-56'])
                    ->description(fn (Task $record): string => $record->contextLabel()),
                TextColumn::make('status')->label(__('Status'))->badge()->sortable(),
                TextColumn::make('priority')->label(__('Priority'))->badge()->sortable()->toggleable()
                    ->state(fn (Task $record): ?TaskPriority => $record->priority->standsOut() ? $record->priority : null),
                TextColumn::make('due_on')
                    ->label(__('Deadline'))
                    ->date()
                    ->sortable()
                    ->color(fn (Task $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('checklist')
                    ->label(__('Checklist'))
                    ->state(fn (Task $record): ?string => $record->checklistProgress())
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tracked_seconds')
                    ->label(__('Tracked'))
                    ->formatStateUsing(fn (mixed $state): ?string => (int) $state > 0 ? Duration::format((int) $state) : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('value')
                    ->label(__('Value'))
                    ->state(fn (Task $record): ?int => $record->value() > 0 ? $record->value() : null)
                    ->formatStateUsing(fn (int $state, Task $record): string => Money::format($state, $record->client->currency))
                    ->description(fn (Task $record): ?string => match (true) {
                        $record->isReadyToBill() => (string) __('Ready to bill'),
                        $record->pricing_type === PricingType::Hourly => null,
                        default => $record->pricing_type->getLabel(),
                    })
                    ->alignEnd(),
            ])
            ->filters([
                ClientFilter::make()->hiddenOn([ClientTasks::class, ProjectTasks::class]),
                SelectFilter::make('project')->label(__('Project'))->relationship('project', 'name')->searchable()->preload()
                    ->hiddenOn(ProjectTasks::class),
                SelectFilter::make('status')->label(__('Status'))->options(TaskStatus::class)->multiple(),
                SelectFilter::make('priority')->label(__('Priority'))->options(TaskPriority::class)->multiple(),
                SelectFilter::make('pricing_type')->label(__('Pricing'))->options(PricingType::class),
                Filter::make('open')->label(__('Open only'))->toggle()->default()
                    ->query(fn (TaskBuilder $query) => $query->open()),
                TernaryFilter::make('is_ongoing')->label(__('Ongoing')),
                Filter::make('overdue')->label(__('Overdue'))->toggle()
                    ->query(fn (TaskBuilder $query) => $query->overdue()),
                ArchivedFilter::make(),
            ])
            ->recordActions([
                TaskActions::startTimer()->iconButton(),
                ActionGroup::make([
                    EditAction::make(),
                    TaskActions::markReadyToBill(),
                    TaskActions::unmarkReadyToBill(),
                    ...ArchiveActions::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No tasks'))
            ->emptyStateDescription(__('Tasks are the unit of work: track time on them and bill them hourly or for a fixed price.'));
    }
}
