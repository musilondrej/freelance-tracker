<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Actions\TimeTracking\StartTimer;
use App\Enums\PricingType;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

final class TaskActions
{
    public static function startTimer(): Action
    {
        return self::confirmSwitch(Action::make('startTimer'))
            ->label(__('Start timer'))
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->visible(fn (Task $record): bool => ! $record->isArchived())
            ->action(fn (Task $record, Component $livewire) => self::start($record, $livewire));
    }

    /** Starts a new timer on the entry's task with the same note. */
    public static function continueEntry(): Action
    {
        return self::confirmSwitch(Action::make('continueEntry'))
            ->label(__('Continue'))
            ->tooltip(__('Continue'))
            ->iconButton()
            ->icon(Heroicon::OutlinedPlay)
            ->color('gray')
            ->hidden(fn (TimeEntry $record): bool => $record->isRunning() || $record->task->isArchived())
            ->action(fn (TimeEntry $record, Component $livewire) => self::start($record->task, $livewire, $record->note));
    }

    private static function confirmSwitch(Action $action): Action
    {
        // A custom heading alone would open the modal every time. Ask only when switching.
        return $action
            ->requiresConfirmation()
            ->modalHidden(fn (): bool => ! TimeEntry::query()->running()->exists())
            ->modalHeading(__('Switch the timer?'))
            ->modalDescription(__('Another timer is running. It will be stopped and saved.'));
    }

    /** Starts the timer, or switches to this task when another timer runs. */
    public static function start(Task $task, Component $livewire, ?string $note = null, ?CarbonImmutable $startedAt = null): void
    {
        app(StartTimer::class)->handle($task, stopRunning: true, note: $note, startedAt: $startedAt);
        $livewire->dispatch('timer-changed');
        Notification::make()->success()->title(__('Timer started: :task', ['task' => $task->title]))->send();
    }

    public static function markReadyToBill(): Action
    {
        return Action::make('markReadyToBill')
            ->label(__('Ready to bill'))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('primary')
            ->visible(fn (Task $record): bool => $record->pricing_type === PricingType::Fixed && ! $record->isReadyToBill())
            ->requiresConfirmation()
            ->modalDescription(__('The fixed price becomes billable and counts as work value from today. It is locked until unmarked.'))
            ->action(fn (Task $record) => $record->markReadyToBill())
            ->successNotificationTitle(__('Marked as ready to bill'));
    }

    public static function unmarkReadyToBill(): Action
    {
        return Action::make('unmarkReadyToBill')
            ->label(__('Not ready to bill'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Task $record): bool => $record->isReadyToBill())
            ->requiresConfirmation()
            ->action(fn (Task $record) => $record->unmarkReadyToBill())
            ->successNotificationTitle(__('Unmarked'));
    }

    public static function recalculateTimeEntries(): Action
    {
        return Action::make('recalculateTimeEntries')
            ->label(__('Recalculate entries'))
            ->icon(Heroicon::OutlinedCalculator)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Time entries not on a statement get the current rate of the task. Entries on a statement never change.'))
            ->action(function (Task $record): void {
                Notification::make()->success()->title(__(':count entries recalculated', ['count' => $record->recalculateTimeEntries()]))->send();
            });
    }
}
