<?php

declare(strict_types=1);

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Filament\Actions\ExportActions;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Attributes\On;

class ManageTimeEntries extends ManageRecords
{
    protected static string $resource = TimeEntryResource::class;

    /** Re-render the table when the global timer starts or stops. */
    #[On('timer-changed')]
    public function refreshEntries(): void {}

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
