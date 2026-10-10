<?php

declare(strict_types=1);

namespace App\Actions\Alerts;

use App\Models\TimeEntry;
use App\Models\User;
use App\Notifications\TimerRunningTooLong;
use App\Support\SentAlert;

final class AlertForgottenTimer
{
    /**
     * Notifies users when the running timer is over their limit. Once per entry and user.
     *
     * @return int Notifications sent.
     */
    public function handle(): int
    {
        $entry = TimeEntry::query()->running()->with('task.client')->first();

        if ($entry === null) {
            return 0;
        }

        $running = $entry->elapsedSeconds();
        $sent = 0;

        foreach (User::query()->whereNotNull('timer_alert_seconds')->where('timer_alert_seconds', '<=', $running)->get() as $user) {
            if (SentAlert::claim("timer:{$entry->id}:user:{$user->id}")) {
                $user->notify(new TimerRunningTooLong($entry));
                $sent++;
            }
        }

        return $sent;
    }
}
