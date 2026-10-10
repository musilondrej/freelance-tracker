<?php

declare(strict_types=1);

namespace App\Actions\TimeTracking;

use App\Exceptions\DomainRuleViolation;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;

final class StopTimer
{
    /**
     * Stops the running timer at server time. If it ran under a second, the entry is deleted
     * and null is returned, because the database rejects a zero duration.
     */
    public function handle(): ?TimeEntry
    {
        return DB::transaction(function (): ?TimeEntry {
            $entry = TimeEntry::query()->running()->lockForUpdate()->first();

            if ($entry === null) {
                throw DomainRuleViolation::because('No timer is running.');
            }

            $endedAt = now()->startOfSecond();

            if ($endedAt->lessThanOrEqualTo($entry->started_at)) {
                $entry->delete();

                return null;
            }

            $entry->update(['ended_at' => $endedAt]);

            return $entry;
        });
    }
}
