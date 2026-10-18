<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Actions\ExportActions;
use App\Filament\Resources\Tasks\TaskResource;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    use RefreshesOnTimerChange;

    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::tasks(),
            CreateAction::make(),
        ];
    }
}
