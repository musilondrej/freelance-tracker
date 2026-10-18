<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Projects\ProjectResource;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\Project;
use App\Support\Duration;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ActiveProjects extends TableWidget
{
    use RefreshesOnTimerChange;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Active projects'))
            ->query(Project::query()
                ->inProgress()
                ->with('client')
                ->withSum('timeEntries', 'duration_seconds')
                ->withBudgetUsage()
                ->orderByBudgetUsage()
                ->orderBy('name'))
            ->paginated([5, 10])
            ->defaultKeySort(false)
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Project'))->wrap()->description(fn (Project $record): string => $record->client->name),
                TextColumn::make('time_entries_sum_duration_seconds')->label(__('Tracked'))
                    ->formatStateUsing(fn (mixed $state): string => Duration::format((int) $state))->alignEnd(),
                TextColumn::make('usage')
                    ->label(__('Budget'))
                    ->state(fn (Project $record): ?string => ($percent = $record->budgetUsagePercent()) === null ? null : $percent.' %')
                    ->description(fn (Project $record): ?string => $record->budget ? Money::format($record->budget, $record->client->currency) : null)
                    ->badge()
                    ->color(fn (Project $record): string => $record->budgetStatus()?->getColor() ?? 'gray')
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->emptyStateHeading(__('No active projects'));
    }
}
