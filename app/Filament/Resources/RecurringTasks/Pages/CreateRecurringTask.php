<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTasks\Pages;

use App\Actions\Recurring\GenerateRecurringTasks;
use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use App\Models\RecurringTask;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringTask extends CreateRecord
{
    protected static string $resource = RecurringTaskResource::class;

    /** Creates the current and any missed periods right away, not at the next scheduler run. */
    protected function afterCreate(): void
    {
        /** @var RecurringTask $record */
        $record = $this->getRecord();
        $count = app(GenerateRecurringTasks::class)->handle($record);

        if ($count > 0) {
            Notification::make()->success()->title(__(':count tasks generated', ['count' => $count]))->send();
        }
    }
}
