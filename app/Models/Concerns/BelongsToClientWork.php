<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of a client, optionally about one of its projects or tasks: notes and links.
 * Composite foreign keys keep the project and task on the same client.
 *
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property-read Client $client
 * @property-read Project|null $project
 * @property-read Task|null $task
 */
trait BelongsToClientWork
{
    protected static function bootBelongsToClientWork(): void
    {
        // A record about a task is also about its project and client.
        static::saving(function (self $record): void {
            if ($record->task_id !== null && $record->isDirty('task_id')) {
                $task = Task::query()->findOrFail($record->task_id);
                $record->client_id = $task->client_id;
                $record->project_id = $task->project_id;
            }
        });
    }

    /** "ACME-12 Title" or the project name, for lists that show records of the whole client. */
    public function subjectLabel(): ?string
    {
        return match (true) {
            $this->task !== null => $this->task->key().' '.$this->task->title,
            $this->project !== null => $this->project->name,
            default => null,
        };
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
