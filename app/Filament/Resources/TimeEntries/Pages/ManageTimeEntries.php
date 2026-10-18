<?php

declare(strict_types=1);

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Filament\Actions\ExportActions;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTimeEntries extends ManageRecords
{
    use RefreshesOnTimerChange;

    protected static string $resource = TimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::timeEntries(),
            CreateAction::make()
                ->label(__('Log time'))
                ->mutateDataUsing(fn (array $data): array => TimeEntryForm::prepare($data)),
        ];
    }
}
