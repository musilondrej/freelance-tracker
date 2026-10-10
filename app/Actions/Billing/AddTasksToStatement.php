<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\PricingType;
use App\Enums\StatementStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Builders\TimeEntryBuilder;
use App\Models\Statement;
use App\Models\StatementItem;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class AddTasksToStatement
{
    public function __construct(private AddTimeEntriesToStatement $addTimeEntries) {}

    /**
     * Adds all unbilled work of the tasks to a draft: hourly entries started before $until
     * and whole fixed prices. Throws when there is nothing to add.
     *
     * @param  iterable<Task>  $tasks
     */
    public function handle(Statement $statement, iterable $tasks, ?CarbonImmutable $until = null): void
    {
        DB::transaction(function () use ($statement, $tasks, $until): void {
            $statement = $statement->lockInStatus(StatementStatus::Draft);
            $added = false;

            foreach ($tasks as $task) {
                $billed = match ($task->pricing_type) {
                    PricingType::Hourly => $this->addHourly($statement, $task, $until),
                    PricingType::Fixed => $this->addFixed($statement, $task),
                    PricingType::NonBillable => throw DomainRuleViolation::because('Task ":task" is non-billable.', ['task' => $task->title]),
                };
                $added = $added || $billed;
            }

            if (! $added) {
                throw DomainRuleViolation::because('There is nothing to bill on the selected tasks.');
            }
        });
    }

    private function addHourly(Statement $statement, Task $task, ?CarbonImmutable $until): bool
    {
        $entryIds = $task->timeEntries()
            ->unbilledHourly()
            ->when($until, fn (TimeEntryBuilder $query) => $query->where('time_entries.started_at', '<', $until))
            ->pluck('time_entries.id')
            ->all();

        if ($entryIds === []) {
            return false;
        }

        $this->addTimeEntries->handle($statement, $entryIds);

        return true;
    }

    private function addFixed(Statement $statement, Task $task): bool
    {
        $task = Task::query()->lockForUpdate()->findOrFail($task->id);

        if (! $task->isReadyToBill() || $task->isFixedPriceBilled()) {
            return false;
        }

        if ($task->client_id !== $statement->client_id) {
            throw DomainRuleViolation::because('The task must belong to the client of the statement.');
        }

        (new StatementItem)->forceFill([
            'statement_id' => $statement->id,
            'task_id' => $task->id,
            'description' => $task->title,
            'pricing_type' => PricingType::Fixed,
            'unit_price' => null,
            'billed_seconds' => 0,
            'amount' => $task->fixed_price,
            'position' => $statement->nextItemPosition(),
        ])->save();

        return true;
    }
}
