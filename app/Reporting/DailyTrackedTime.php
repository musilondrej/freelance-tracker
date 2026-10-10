<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\TimeEntry;
use App\Support\Period;

/**
 * Tracked time per local day, split by the client's currency. Unlike money, time of different
 * currencies may be added up.
 */
final class DailyTrackedTime
{
    /**
     * @return array<string, array<string, int>> currency code, then local date like "2026-10-11", then seconds. Days without time are missing.
     */
    public static function for(Period $period): array
    {
        $rows = TimeEntry::query()
            ->startedIn($period)
            ->toBase()
            ->selectRaw('time_entries.currency, (time_entries.started_at AT TIME ZONE ?)::date::text AS day, SUM(time_entries.duration_seconds)::bigint AS seconds', [Period::timezone()])
            ->groupBy('time_entries.currency', 'day')
            ->orderBy('time_entries.currency')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row->currency][(string) $row->day] = (int) $row->seconds;
        }

        return $result;
    }
}
