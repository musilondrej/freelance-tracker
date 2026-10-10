<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Support\Period;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One digest of all upcoming deadlines. */
final class DeadlinesApproaching extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  Collection<int, Task>  $tasks  Ordered by deadline. */
    public function __construct(private readonly Collection $tasks) {}

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
            ->icon(Heroicon::OutlinedCalendar)
            ->title($this->title())
            ->body(implode(' · ', $this->lines()))
            ->actions([Action::make('open')->label(__('Open'))->url($this->url())])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->greeting(__('Hello!'));

        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->action(__('Open'), $this->url());
    }

    private function title(): string
    {
        return $this->tasks->count() === 1
            ? __('Deadline coming up: :task', ['task' => $this->tasks->first()?->title])
            : __(':count deadlines coming up', ['count' => $this->tasks->count()]);
    }

    /** @return array<int, string> "ACME-12 Login form – 16. 10." */
    private function lines(): array
    {
        return array_map(
            fn (Task $task): string => $task->key().' '.$task->title.' – '.$task->due_on?->format(Period::SHORT_DATE_FORMAT),
            $this->tasks->all(),
        );
    }

    private function url(): string
    {
        return $this->tasks->count() === 1 && $this->tasks->first() instanceof Task
            ? TaskResource::getUrl('view', ['record' => $this->tasks->first()])
            : TaskResource::getUrl('index');
    }
}
