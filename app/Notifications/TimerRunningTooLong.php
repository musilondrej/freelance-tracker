<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Duration;
use App\Support\Period;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Queued: a slow mail server never holds up the scheduler. */
final class TimerRunningTooLong extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly TimeEntry $entry) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->notificationChannels();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->warning()
            ->icon(Heroicon::OutlinedClock)
            ->title($this->title())
            ->body($this->body())
            ->actions([Action::make('open')->label(__('Open time tracking'))->url(TimeEntryResource::getUrl())])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello!'))
            ->line($this->body())
            ->line(__('If you forgot to stop it, stop it and correct the end time.'))
            ->action(__('Open time tracking'), TimeEntryResource::getUrl());
    }

    private function title(): string
    {
        return __('The timer has been running for :duration h', ['duration' => Duration::format($this->entry->elapsedSeconds())]);
    }

    private function body(): string
    {
        return __('Task ":task" (:client), started :start.', [
            'task' => $this->entry->task->title,
            'client' => $this->entry->task->client->name,
            'start' => $this->entry->started_at->setTimezone(Period::timezone())->format(Period::DATETIME_FORMAT),
        ]);
    }
}
