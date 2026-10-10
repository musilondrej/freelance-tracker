<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\PricingType;
use App\Models\TimeEntry;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @template TModel of TimeEntry
 *
 * @extends Builder<TModel>
 */
final class TimeEntryBuilder extends Builder
{
    public function running(): static
    {
        return $this->whereNull('time_entries.ended_at');
    }

    public function finished(): static
    {
        return $this->whereNotNull('time_entries.ended_at');
    }

    /** Snapshot pricing is hourly, so the entry has an amount. */
    public function hourly(): static
    {
        return $this->where('time_entries.pricing_type', PricingType::Hourly);
    }

    /** Hourly and fixed-price work; their time counts towards the average rate. */
    public function billable(): static
    {
        return $this->where('time_entries.pricing_type', '!=', PricingType::NonBillable);
    }

    public function nonBillable(): static
    {
        return $this->where('time_entries.pricing_type', PricingType::NonBillable);
    }

    /** Finished entries that started within the period. */
    public function startedIn(Period $period): static
    {
        return $this->finished()
            ->where('time_entries.started_at', '>=', $period->start)
            ->where('time_entries.started_at', '<', $period->end);
    }

    /** On a draft or sent statement. */
    public function allocated(): static
    {
        return $this->whereExists($this->allocationSubquery());
    }

    /** Adds `is_allocated`, the same rule as allocated(), for lists that show it per row. */
    public function withAllocation(): static
    {
        return $this->withExists('statementItems as is_allocated');
    }

    public function unallocated(): static
    {
        return $this->whereNotExists($this->allocationSubquery());
    }

    /** Finished hourly work that is not on any statement yet. The hourly part of "to invoice". */
    public function unbilledHourly(): static
    {
        return $this->finished()->unallocated()->hourly();
    }

    private function allocationSubquery(): QueryBuilder
    {
        return DB::table('statement_item_time_entry')->whereColumn('statement_item_time_entry.time_entry_id', 'time_entries.id');
    }
}
