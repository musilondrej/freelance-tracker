<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @template TModel of Project
 *
 * @extends ArchivableBuilder<TModel>
 */
final class ProjectBuilder extends ArchivableBuilder
{
    /**
     * Adds all-time `consumed` and `pending` in minor units, for the Project budget methods in lists.
     * Consumed is the work value: hourly entries plus ready fixed prices. Pending are fixed prices not ready yet.
     */
    public function withBudgetUsage(): static
    {
        if ($this->query->columns === null) {
            $this->select('projects.*');
        }

        return $this
            ->selectSub($this->work(...$this->consumedParts())->selectRaw('COALESCE(SUM(work.amount), 0)::bigint'), 'consumed')
            ->selectSub($this->work($this->fixedPrices(Task::query()->pendingFixed()))->selectRaw('COALESCE(SUM(work.amount), 0)::bigint'), 'pending');
    }

    /** Highest share of the budget consumed first, projects without a budget last. */
    public function orderByBudgetUsage(): static
    {
        return $this->orderByDesc($this->work(...$this->consumedParts())
            ->selectRaw('COALESCE(COALESCE(SUM(work.amount), 0)::numeric / NULLIF(projects.budget, 0), -1)'));
    }

    /**
     * Amounts of `projects.id` that consume the budget, each part selecting `amount`.
     *
     * @return list<Builder<TimeEntry>|Builder<Task>>
     */
    private function consumedParts(): array
    {
        return [
            TimeEntry::query()
                ->finished()
                ->hourly()
                ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
                ->whereColumn('tasks.project_id', 'projects.id')
                ->select('time_entries.amount'),
            $this->fixedPrices(Task::query()->readyFixed()),
        ];
    }

    /**
     * @param  TaskBuilder<Task>  $tasks
     * @return TaskBuilder<Task>
     */
    private function fixedPrices(TaskBuilder $tasks): TaskBuilder
    {
        return $tasks->whereColumn('tasks.project_id', 'projects.id')->select('tasks.fixed_price as amount');
    }

    /**
     * The parts as one `work` table with an `amount` column, for a correlated subquery.
     *
     * @param  Builder<TimeEntry>|Builder<Task>  $first
     * @param  Builder<TimeEntry>|Builder<Task>  ...$rest
     */
    private function work(Builder $first, Builder ...$rest): QueryBuilder
    {
        $union = $first->toBase();

        foreach ($rest as $part) {
            $union->unionAll($part->toBase());
        }

        return DB::query()->fromSub($union, 'work');
    }
}
