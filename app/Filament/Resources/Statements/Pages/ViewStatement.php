<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Pages;

use App\Filament\Actions\StatementActions;
use App\Filament\Resources\Statements\StatementResource;
use App\Models\Statement;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class ViewStatement extends ViewRecord
{
    protected static string $resource = StatementResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return $record instanceof Statement ? StatementResource::title($record) : $this->getRecordTitle();
    }

    /** Refreshes header totals and actions when items change in the relation manager. */
    #[On('refresh-statement')]
    public function refreshStatement(): void
    {
        $this->getRecord()->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            StatementActions::downloadPdf(),
            StatementActions::send(),
            EditAction::make()->visible(fn (Statement $record): bool => $record->isDraft()),
            ActionGroup::make([
                StatementActions::cancel(),
                DeleteAction::make()
                    ->visible(fn (Statement $record): bool => $record->isDraft())
                    ->modalDescription(__('All its work becomes billable again.')),
            ]),
        ];
    }
}
