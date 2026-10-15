<?php

declare(strict_types=1);

namespace App\Actions\TimeTracking;

use App\Exceptions\DomainRuleViolation;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class StopTimer
{
    /**
     * Stops the running timer at server time, or at $endedAt in the past. If it ran under a second,
     * the entry is deleted and null is returned, because the database rejects a zero duration.
     */
    public function handle(?CarbonImmutable $endedAt = null): ?TimeEntry
    {
        return DB::transaction(function () use ($endedAt): ?TimeEntry {
            $entry = TimeEntry::query()->running()->lockForUpdate()->first();

            if ($entry === null) {
                throw DomainRuleViolation::because('No timer is running.');
            }

            if ($endedAt instanceof CarbonImmutable && $endedAt->lessThanOrEqualTo($entry->started_at)) {
                throw DomainRuleViolation::because('The new start is before the running timer started.');
            }

            $endedAt = ($endedAt ?? now())->startOfSecond();

            if ($endedAt->lessThanOrEqualTo($entry->started_at)) {
                $entry->delete();

                return null;
            }

            $entry->update(['ended_at' => $endedAt]);

            return $entry;
        });
    }
}
