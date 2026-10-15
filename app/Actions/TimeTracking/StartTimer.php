<?php

declare(strict_types=1);

namespace App\Actions\TimeTracking;

use App\Exceptions\DomainRuleViolation;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class StartTimer
{
    public function __construct(private StopTimer $stopTimer) {}

    /**
     * With $stopRunning the running timer is stopped first, in the same transaction.
     * A past $startedAt also ends the stopped timer at that moment, so the two entries do not overlap.
     * The unique index `time_entries_one_running` blocks a second running timer.
     */
    public function handle(Task $task, bool $stopRunning = false, ?string $note = null, ?CarbonImmutable $startedAt = null): TimeEntry
    {
        if ($task->isArchived()) {
            throw DomainRuleViolation::because('Time cannot be tracked on an archived task.');
        }

        if ($startedAt?->isFuture()) {
            throw DomainRuleViolation::because('A timer cannot start in the future.');
        }

        return DB::transaction(function () use ($task, $stopRunning, $note, $startedAt): TimeEntry {
            if ($stopRunning && TimeEntry::query()->running()->exists()) {
                $this->stopTimer->handle($startedAt);
            }

            try {
                // Nested transaction = savepoint, so a violation does not abort the caller's transaction.
                return DB::transaction(fn (): TimeEntry => TimeEntry::query()->create([
                    'task_id' => $task->id,
                    'started_at' => $startedAt ?? now(),
                    'note' => $note,
                ]));
            } catch (UniqueConstraintViolationException) {
                throw DomainRuleViolation::because('A timer is already running. Stop it first.');
            }
        });
    }
}
