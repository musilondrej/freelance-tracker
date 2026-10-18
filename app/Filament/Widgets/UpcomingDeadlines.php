<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Support\Period;
use Carbon\CarbonInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingDeadlines extends TableWidget
{
    private const int DAYS_AHEAD = 14;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Upcoming deadlines'))
            ->query(Task::query()
                ->active()
                ->with(['client', 'project'])
                ->dueBy(self::DAYS_AHEAD)
                ->orderBy('due_on'))
            ->paginated([5, 10])
            ->defaultKeySort(false)
            ->recordUrl(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label(__('Task'))->wrap()
                    ->description(fn (Task $record): string => $record->contextLabel()),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('due_on')->label(__('Deadline'))->date()
                    ->color(fn (Task $record): ?string => $record->isOverdue() ? 'danger' : ($record->isDueToday() ? 'warning' : null))
                    ->description(fn (Task $record): ?string => $record->due_on?->diffForHumans(Period::localToday(), ['parts' => 1, 'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW])),
            ])
            ->emptyStateHeading(__('No deadlines in the next :days days', ['days' => self::DAYS_AHEAD]))
            ->emptyStateIcon(Heroicon::OutlinedCalendar);
    }
}
