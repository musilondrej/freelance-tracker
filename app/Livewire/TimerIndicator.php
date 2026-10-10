<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\TimeTracking\StopTimer;
use App\Filament\Actions\TaskActions;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\Duration;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Global timer in the topbar and on the dashboard. The server keeps the time;
 * the browser only updates the display.
 */
class TimerIndicator extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public bool $large = false;

    #[On('timer-changed')]
    public function refreshTimer(): void
    {
        unset($this->running);
    }

    #[Computed]
    public function running(): ?TimeEntry
    {
        return TimeEntry::query()->running()->with(['task.client', 'task.project'])->first();
    }

    public function stop(): void
    {
        $entry = app(StopTimer::class)->handle();

        $this->dispatch('timer-changed');

        Notification::make()
            ->success()
            ->title($entry === null ? __('Timer discarded (under a second)') : __('Saved :duration on :task', [
                'duration' => Duration::format($entry->duration_seconds),
                'task' => $entry->task->title,
            ]))
            ->send();
    }

    public function startAction(): Action
    {
        return Action::make('start')
            ->label(__('Start timer'))
            ->icon(Heroicon::OutlinedPlay)
            ->color($this->large ? 'primary' : 'gray')
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('Start'))
            ->schema([
                Select::make('task_id')
                    ->label(__('Task'))
                    ->options(fn (): array => $this->taskOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('note')->label(__('Note'))->maxLength(1000),
            ])
            ->action(fn (array $data) => TaskActions::start(Task::query()->findOrFail((int) $data['task_id']), $this, $data['note'] ?? null));
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
            ->withMax('timeEntries as last_tracked_at', 'started_at')
            ->orderByRaw('last_tracked_at DESC NULLS LAST')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Task $task): array => [$task->id => $task->optionLabel()])
            ->all();
    }
}
