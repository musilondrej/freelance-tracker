<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\TimeTracking\StopTimer;
use App\Filament\Actions\TaskActions;
use App\Filament\Forms\DurationInput;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Global timer in the topbar and on the dashboard. The server keeps the time;
 * the browser only updates the display.
 *
 * @property-read ?TimeEntry $running
 * @property-read Collection<int, Task> $recentTasks
 */
class TimerIndicator extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RefreshesOnTimerChange;

    public bool $large = false;

    #[Computed]
    public function running(): ?TimeEntry
    {
        return TimeEntry::query()->running()->with(['task.client', 'task.project'])->first();
    }

    /**
     * Quick restart in the topbar: the last tracked open tasks, without the running one.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function recentTasks(): Collection
    {
        return Task::query()
            ->active()
            ->open()
            ->has('timeEntries')
            ->whereKeyNot($this->running?->task_id)
            ->with(['client', 'project'])
            ->latestTrackedFirst()
            ->limit(7)
            ->get();
    }

    public function restartAction(): Action
    {
        return TaskActions::startTimer()
            ->record(fn (array $arguments): ?Task => Task::query()->find((int) ($arguments['task'] ?? 0)));
    }

    public function stop(): void
    {
        $this->saved(app(StopTimer::class)->handle());
    }

    public function startAction(): Action
    {
        return Action::make('start')
            ->label(__('Start timer'))
            ->icon(Heroicon::OutlinedPlay)
            ->color($this->large ? 'primary' : 'gray')
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('Save'))
            ->schema([
                Select::make('task_id')
                    ->label(__('Task'))
                    ->options(fn (): array => $this->taskOptions())
                    ->searchable()
                    ->required(),
                DateTimePicker::make('started_at')
                    ->label(__('Start'))
                    ->seconds(false)
                    ->placeholder(__('Now'))
                    ->requiredWith('ended_at,duration'),
                DateTimePicker::make('ended_at')
                    ->label(__('End'))
                    ->seconds(false)
                    ->after('started_at')
                    ->helperText(__('Without an end or duration the timer starts running.')),
                DurationInput::make('duration')
                    ->label(__('Duration'))
                    ->helperText(__('Overrides the end. E.g. 1:30, 90m, 2h 15m or 1,5')),
                TextInput::make('note')->label(__('Note'))->maxLength(1000),
            ])
            ->action(fn (array $data) => $this->startOrLog(TimeEntryForm::prepare($data)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function startOrLog(array $data): void
    {
        $task = Task::query()->findOrFail((int) $data['task_id']);
        $startedAt = filled($data['started_at']) ? CarbonImmutable::parse((string) $data['started_at']) : null;

        if (blank($data['ended_at'])) {
            TaskActions::start($task, $this, $data['note'], $startedAt);

            return;
        }

        $this->saved(TimeEntry::query()->create([
            'task_id' => $task->id,
            'started_at' => $startedAt,
            'ended_at' => $data['ended_at'],
            'note' => $data['note'],
        ]));
    }

    /** Null when a timer under a second was discarded. */
    private function saved(?TimeEntry $entry): void
    {
        $this->dispatch('timer-changed');

        Notification::make()
            ->success()
            ->title($entry instanceof TimeEntry ? __('Saved :duration on :task', [
                'duration' => Duration::format($entry->duration_seconds),
                'task' => $entry->task->title,
            ]) : __('Timer discarded (under a second)'))
            ->send();
    }

    public function render(): View
    {
        return view('livewire.timer-indicator');
    }

    /**
     * @return array<int, string>
     */
    private function taskOptions(): array
    {
        return Task::query()
            ->active()
            ->open()
            ->with(['client', 'project'])
            ->latestTrackedFirst()
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Task $task): array => [$task->id => $task->optionLabel()])
            ->all();
    }
}
