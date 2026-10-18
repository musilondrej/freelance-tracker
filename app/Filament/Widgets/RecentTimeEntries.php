<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Actions\TaskActions;
use App\Filament\Resources\TimeEntries\Tables\TimeEntriesTable;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\TimeEntry;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentTimeEntries extends TableWidget
{
    use RefreshesOnTimerChange;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Same columns as the list, including the inline note. Editing and bulk work stay on the list page; continuing a past entry is one click.
        return TimeEntriesTable::configure($table->query(TimeEntry::query()))
            ->heading(__('Recent time entries'))
            ->filters([])
            ->recordActions([TaskActions::continueEntry()])
            ->toolbarActions([])
            ->headerActions([
                Action::make('all')->label(__('Show all'))->link()->url(TimeEntryResource::getUrl()),
            ])
            ->paginated([5, 10, 25]);
    }
}
