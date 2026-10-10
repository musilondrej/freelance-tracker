<?php

declare(strict_types=1);

namespace App\Actions\Alerts;

use App\Models\RecurringTask;
use App\Models\User;
use App\Notifications\RecurringPeriodUpcoming;
use App\Support\Period;
use App\Support\SentAlert;

final class RemindUpcomingRecurring
{
    /**
     * Reminds users of recurring periods starting within their reminder window. Once per period and user.
     *
     * @return int Notifications sent.
     */
    public function handle(): int
    {
        $today = Period::localToday();
        $users = User::query()->whereNotNull('recurring_reminder_days')->get();

        if ($users->isEmpty()) {
            return 0;
        }

        $recurring = RecurringTask::query()
            ->generating()
            ->with('client')
            ->get();

        $sent = 0;

        foreach ($recurring as $item) {
            $start = $item->nextPeriodStart();

            if ($start === null || $start->lessThanOrEqualTo($today)) {
                continue;
            }

            $daysAhead = (int) $today->diffInDays($start);

            foreach ($users as $user) {
                if ($daysAhead <= $user->recurring_reminder_days && SentAlert::claim("recurring:{$item->id}:{$start->toDateString()}:user:{$user->id}")) {
                    $user->notify(new RecurringPeriodUpcoming($item, $start));
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
