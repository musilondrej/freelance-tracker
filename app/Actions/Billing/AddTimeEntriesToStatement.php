<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\PricingType;
use App\Enums\StatementStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Statement;
use App\Models\StatementItem;
use App\Models\TimeEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AddTimeEntriesToStatement
{
    /**
     * Adds finished hourly entries to a draft, one item per task and rate.
     * The item amount is the sum of the entry amounts.
     *
     * @param  array<int>  $timeEntryIds
     */
    public function handle(Statement $statement, array $timeEntryIds): void
    {
        DB::transaction(function () use ($statement, $timeEntryIds): void {
            $statement = $statement->lockInStatus(StatementStatus::Draft);

            $ids = collect($timeEntryIds)->unique();

            $entries = TimeEntry::query()
                ->with('task')
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty() || $entries->count() !== $ids->count()) {
                throw DomainRuleViolation::because('Some of the selected time entries do not exist.');
            }

            if (TimeEntry::query()->whereKey($entries->modelKeys())->allocated()->exists()) {
                throw DomainRuleViolation::because('Some of the selected time entries are already billed.');
            }

            foreach ($entries as $entry) {
                $this->assertBillable($statement, $entry);
            }

            $entries
                ->groupBy(fn (TimeEntry $entry): string => $entry->task_id.'|'.$entry->hourly_rate)
                ->each(fn (Collection $group) => $this->allocate($statement, $group));
        });
    }

    private function assertBillable(Statement $statement, TimeEntry $entry): void
    {
        if ($entry->isRunning()) {
            throw DomainRuleViolation::because('A running timer cannot be billed.');
        }

        if ($entry->pricing_type !== PricingType::Hourly) {
            throw DomainRuleViolation::because('Only hourly time entries can be billed by time.');
        }

        if ($entry->task->client_id !== $statement->client_id || $entry->currency !== $statement->currency) {
            throw DomainRuleViolation::because('Time entries must belong to the client and currency of the statement.');
        }
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function allocate(Statement $statement, Collection $entries): void
    {
        /** @var TimeEntry $first */
        $first = $entries->first();

        $item = StatementItem::query()
            ->where('statement_id', $statement->id)
            ->where('task_id', $first->task_id)
            ->where('pricing_type', PricingType::Hourly)
            ->where('unit_price', $first->hourly_rate)
            ->first()
            ?? (new StatementItem)->forceFill([
                'statement_id' => $statement->id,
                'task_id' => $first->task_id,
                'description' => $first->task->title,
                'pricing_type' => PricingType::Hourly,
                'unit_price' => $first->hourly_rate,
                'billed_seconds' => 0,
                'amount' => 0,
                'position' => $statement->nextItemPosition(),
            ]);

        $item->forceFill([
            'billed_seconds' => $item->billed_seconds + (int) $entries->sum('duration_seconds'),
            'amount' => $item->amount + (int) $entries->sum('amount'),
        ])->save();

        try {
            // Savepoint: UNIQUE(time_entry_id) catches the same entry being billed twice at once.
            DB::transaction(fn () => $item->timeEntries()->attach($entries->pluck('id')));
        } catch (UniqueConstraintViolationException) {
            throw DomainRuleViolation::because('Some of the selected time entries are already billed.');
        }
    }
}
