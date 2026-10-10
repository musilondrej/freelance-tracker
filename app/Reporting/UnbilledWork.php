<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\PricingType;
use App\Models\Builders\TaskBuilder;
use App\Models\Builders\TimeEntryBuilder;
use App\Models\Task;

/**
 * Tasks with work that is not on any statement yet, for the "To invoice" list.
 * Same definition as CurrencyTotals::$unbilled, per task.
 */
final class UnbilledWork
{
    /**
     * Adds unbilled_hourly, unbilled_seconds and unbilled_since columns.
     *
     * @return TaskBuilder<Task>
     */
    public static function tasks(): TaskBuilder
    {
        $unbilledHourly = fn (TimeEntryBuilder $entries) => $entries->unbilledHourly();

        return Task::query()
            ->select('tasks.*')
            ->withSum(['timeEntries as unbilled_hourly' => $unbilledHourly], 'amount')
            ->withSum(['timeEntries as unbilled_seconds' => $unbilledHourly], 'duration_seconds')
            ->withMin(['timeEntries as unbilled_since' => $unbilledHourly], 'started_at')
            ->where(fn (TaskBuilder $query) => $query
                ->whereHas('timeEntries', $unbilledHourly)
                ->orWhere(fn (TaskBuilder $fixed) => $fixed->unbilledFixed()));
    }

    /** For a task loaded by tasks(). */
    public static function amount(Task $task): int
    {
        return match ($task->pricing_type) {
            PricingType::Hourly => (int) $task->getAttribute('unbilled_hourly'),
            PricingType::Fixed => (int) $task->fixed_price,
            PricingType::NonBillable => 0,
        };
    }
}
