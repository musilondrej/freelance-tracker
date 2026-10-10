<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MoveTask
{
    /**
     * Moves a task in front of $before (or to the end of the column) and renumbers the column.
     * It uses a neighbour, not an index, so the order stays right on a filtered board.
     */
    public function handle(Task $task, TaskStatus $status, ?Task $before = null): void
    {
        DB::transaction(function () use ($task, $status, $before): void {
            $column = $this->lockColumnWithout($task, $status);
            $position = $this->positionInFrontOf($before, $column);
            $column->splice($position, 0, [$task->id]);

            // Through the model, so its events see the status change (completed_at).
            $task->update(['status' => $status, 'position' => $position]);

            $this->renumber($column);
        });
    }

    /**
     * @return Collection<int, int> task ids in board order
     */
    private function lockColumnWithout(Task $task, TaskStatus $status): Collection
    {
        return Task::query()
            ->active()
            ->where('status', $status)
            ->whereKeyNot($task->id)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $column
     */
    private function positionInFrontOf(?Task $before, Collection $column): int
    {
        $index = $before instanceof Task ? $column->search($before->id, strict: true) : false;

        return $index === false ? $column->count() : $index;
    }

    /**
     * Saves each task's index in the column as its position.
     *
     * @param  Collection<int, int>  $column
     */
    private function renumber(Collection $column): void
    {
        $ids = '{'.$column->implode(',').'}';
        $positions = '{'.$column->keys()->implode(',').'}';

        DB::update(<<<'SQL'
            UPDATE tasks
            SET position = moved.position
            FROM unnest(?::bigint[], ?::int[]) AS moved(id, position)
            WHERE tasks.id = moved.id AND tasks.position <> moved.position
            SQL, [$ids, $positions]);
    }
}
