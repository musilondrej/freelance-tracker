<?php

declare(strict_types=1);

namespace App\Actions\Recurring;

use App\Enums\BillingTiming;
use App\Enums\PricingType;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskStatus;
use App\Models\Builders\RecurringTaskBuilder;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a task for every recurring period that has started, including missed ones.
 * Marks fixed prices billed in arrears as ready after their period ends. Safe to run any time.
 */
final class GenerateRecurringTasks
{
    /**
     * @return int Number of tasks created.
     */
    public function handle(?RecurringTask $only = null): int
    {
        $today = Period::localToday()->toDateString();

        $ids = RecurringTask::query()
            ->generating()
            ->when($only, fn (RecurringTaskBuilder $query, RecurringTask $recurring) => $query->whereKey($recurring->id))
            ->pluck('id');

        $created = 0;

        foreach ($ids as $id) {
            $created += $this->generate((int) $id, $today);
        }

        $this->markArrearsReady($today);

        return $created;
    }

    private function generate(int $id, string $today): int
    {
        return DB::transaction(function () use ($id, $today): int {
            $recurring = RecurringTask::query()->lockForUpdate()->findOrFail($id);
            $created = 0;

            while (($start = $recurring->nextPeriodStart()) !== null && $start->toDateString() <= $today) {
                $k = $recurring->periods_generated;
                $end = $recurring->periodEnd($k);

                $task = (new Task)->forceFill([
                    'client_id' => $recurring->client_id,
                    'project_id' => $recurring->project_id,
                    'recurring_task_id' => $recurring->id,
                    'period_start' => $start,
                    'period_end' => $end,
                    'title' => $recurring->title.' – '.self::periodLabel($recurring, $start, $end),
                    'description' => $recurring->description,
                    'status' => TaskStatus::Todo,
                    'priority' => $recurring->priority,
                    'pricing_type' => $recurring->pricing_type,
                    'hourly_rate' => $recurring->hourly_rate,
                    'fixed_price' => $recurring->fixed_price,
                    'estimated_seconds' => $recurring->estimated_seconds,
                    'due_on' => $end,
                ]);
                $task->save();

                if ($recurring->pricing_type === PricingType::Fixed && $recurring->billing_timing === BillingTiming::InAdvance) {
                    $task->markReadyToBill(self::startOfLocalDay($start));
                }

                $recurring->forceFill(['periods_generated' => $k + 1])->save();
                $created++;
            }

            return $created;
        });
    }

    /** Fixed prices billed in arrears become billable the day after their period ends. */
    private function markArrearsReady(string $today): void
    {
        Task::query()
            ->pendingFixed()
            ->where('period_end', '<', $today)
            // The template must be fixed too. A task switched to fixed by hand is never marked, see docs/billing.md.
            ->whereHas('recurringTask', fn (RecurringTaskBuilder $query) => $query
                ->where('pricing_type', PricingType::Fixed)
                ->where('billing_timing', BillingTiming::InArrears))
            ->get()
            ->each(fn (Task $task) => $task->markReadyToBill(
                self::startOfLocalDay(($task->period_end ?? CarbonImmutable::parse($today))->addDay()),
            ));
    }

    /** Month and year, quarter and year, year, or a date range for irregular periods. */
    private static function periodLabel(RecurringTask $recurring, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $aligned = $recurring->interval === 1 && $start->day === 1;

        return match (true) {
            $aligned && $recurring->frequency === RecurrenceFrequency::Monthly => $start->settings(['locale' => 'cs'])->isoFormat('MMMM YYYY'),
            $aligned && $recurring->frequency === RecurrenceFrequency::Quarterly && $start->month % 3 === 1 => __(':quarter. quarter :year', ['quarter' => $start->quarter, 'year' => $start->year]),
            $aligned && $recurring->frequency === RecurrenceFrequency::Yearly && $start->month === 1 => (string) $start->year,
            default => $start->format(Period::DATE_FORMAT).' – '.$end->format(Period::DATE_FORMAT),
        };
    }

    private static function startOfLocalDay(CarbonImmutable $date): CarbonImmutable
    {
        return Period::day($date->toDateString())->start;
    }
}
