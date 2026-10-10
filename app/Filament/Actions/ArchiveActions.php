<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

final class ArchiveActions
{
    /**
     * @return array{Action, Action}
     */
    public static function make(): array
    {
        return [
            Action::make('archive')
                ->label(__('Archive'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('The record disappears from daily lists but stays in reports and history.'))
                ->visible(fn (Client|Project|RecurringTask|Task $record): bool => ! $record->isArchived())
                ->action(fn (Client|Project|RecurringTask|Task $record) => $record->archive())
                ->successNotificationTitle(__('Archived')),
            Action::make('unarchive')
                ->label(__('Restore from archive'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (Client|Project|RecurringTask|Task $record): bool => $record->isArchived())
                ->action(fn (Client|Project|RecurringTask|Task $record) => $record->unarchive())
                ->successNotificationTitle(__('Restored')),
        ];
    }
}
