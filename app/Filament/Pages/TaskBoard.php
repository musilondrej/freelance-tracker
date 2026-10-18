<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Tasks\MoveTask;
use App\Enums\NavigationGroup;
use App\Enums\TaskStatus;
use App\Filament\Actions\TaskActions;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\Builders\ProjectBuilder;
use App\Models\Builders\TaskBuilder;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Kanban of active tasks by status. Drag and drop uses Livewire `wire:sort` and MoveTask.
 */
class TaskBoard extends Page
{
    use RefreshesOnTimerChange;

    /** Done tasks older than this are hidden from the board. */
    private const int DONE_VISIBLE_DAYS = 14;

    protected string $view = 'filament.pages.task-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Work;

    protected static ?int $navigationSort = 2;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Url(as: 'client')]
    public ?int $clientId = null;

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public static function getNavigationLabel(): string
    {
        return __('Kanban');
    }

    public function getTitle(): string
    {
        return __('Kanban');
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function clients(): array
    {
        return Client::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function projects(): array
    {
        return Project::query()
            ->when($this->clientId, fn (ProjectBuilder $q, int $id) => $q->where('client_id', $id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedClientId(): void
    {
        $this->projectId = null;
    }

    /**
     * @return array<string, Collection<int, Task>>
     */
    #[Computed]
    public function columns(): array
    {
        $tasks = Task::query()
            ->active()
            ->with(['client', 'project'])
            ->withTracking()
            ->withChecklistProgress()
            ->when($this->clientId, fn (TaskBuilder $q, int $id) => $q->where('client_id', $id))
            ->when($this->projectId, fn (TaskBuilder $q, int $id) => $q->where('project_id', $id))
            ->onBoard(self::DONE_VISIBLE_DAYS)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task): string => $task->status->value);

        return collect(TaskStatus::onBoard())
            ->mapWithKeys(fn (TaskStatus $status): array => [$status->value => $tasks->get($status->value, new Collection)->values()])
            ->all();
    }

    /**
     * Called by wire:sort. The index is in the visible column, which may be filtered,
     * so it is turned into the task the dragged one was dropped in front of.
     */
    public function move(string $taskId, int $position, string $status): void
    {
        $status = TaskStatus::from($status);
        $task = Task::query()->findOrFail((int) $taskId);

        $before = $this->columns()[$status->value]
            ->reject(fn (Task $visible): bool => $visible->id === $task->id)
            ->values()
            ->get($position);

        app(MoveTask::class)->handle($task, $status, $before);

        unset($this->columns);
    }

    public function startTimerAction(): Action
    {
        return TaskActions::startTimer()
            ->record(fn (array $arguments): ?Task => Task::query()->find((int) ($arguments['task'] ?? 0)))
            ->iconButton()
            ->size(Size::Small)
            ->color('gray');
    }
}
