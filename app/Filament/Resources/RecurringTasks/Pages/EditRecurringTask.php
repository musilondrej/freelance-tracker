<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTasks\Pages;

use App\Filament\Actions\ArchiveActions;
use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditRecurringTask extends EditRecord
{
    protected static string $resource = RecurringTaskResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ...ArchiveActions::make(),
                DeleteAction::make()->modalDescription(__('Generated tasks are kept.')),
            ]),
        ];
    }
}
