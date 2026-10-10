<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
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
            $siblings = Task::query()
                ->active()
                ->where('status', $status)
                ->whereKeyNot($task->id)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->values();

            $anchor = $before instanceof Task ? $siblings->search($before->id, strict: true) : false;
            $position = $anchor === false ? $siblings->count() : $anchor;
            $siblings->splice($position, 0, [$task->id]);

            // Through the model, so its events see the status change (completed_at).
            $task->update(['status' => $status, 'position' => $position]);

            // The rest of the column in one statement instead of one UPDATE per card.
            DB::update(
                'UPDATE tasks SET position = v.position FROM unnest(?::bigint[], ?::int[]) AS v(id, position)
                    WHERE tasks.id = v.id AND tasks.position <> v.position',
                ['{'.$siblings->implode(',').'}', '{'.$siblings->keys()->implode(',').'}'],
            );
        });
    }
}
