<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTasks\Pages;

use App\Actions\Recurring\GenerateRecurringTasks;
use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRecurringTasks extends ListRecords
{
    protected static string $resource = RecurringTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(__('Generate now'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn (GenerateRecurringTasks $generate) => Notification::make()
                    ->success()
                    ->title(__(':count tasks generated', ['count' => $generate->handle()]))
                    ->send()),
            CreateAction::make(),
        ];
    }
}
