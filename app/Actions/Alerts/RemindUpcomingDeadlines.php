<?php

declare(strict_types=1);

namespace App\Actions\Alerts;

use App\Models\Task;
use App\Models\User;
use App\Notifications\DeadlinesApproaching;
use App\Support\SentAlert;

final class RemindUpcomingDeadlines
{
    /**
     * One notification per user with the open tasks newly inside their deadline window.
     * Each task is announced once per deadline; moving the deadline announces it again.
     *
     * @return int Notifications sent.
     */
    public function handle(): int
    {
        $sent = 0;

        foreach (User::query()->whereNotNull('deadline_reminder_days')->get() as $user) {
            $tasks = Task::query()
                ->active()
                ->dueWithin((int) $user->deadline_reminder_days)
                ->with('client')
                ->orderBy('due_on')
                ->get()
                ->filter(fn (Task $task): bool => SentAlert::claim("deadline:{$task->id}:{$task->due_on?->toDateString()}:user:{$user->id}"))
                ->values();

            if ($tasks->isNotEmpty()) {
                $user->notify(new DeadlinesApproaching($tasks));
                $sent++;
            }
        }

        return $sent;
    }
}
