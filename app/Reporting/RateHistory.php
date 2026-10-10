<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\Currency;
use App\Models\TimeEntry;
use App\Support\Period;
use Carbon\CarbonImmutable;

/**
 * Hourly rates used on time entries, per client, within a period.
 */
final class RateHistory
{
    /**
     * @return array<int, RateLine>
     */
    public static function for(Period $period, ?int $clientId = null, ?int $projectId = null): array
    {
        $tz = Period::timezone();

        $query = TimeEntry::query()
            ->startedIn($period)
            ->hourly()
            ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->join('clients', 'clients.id', '=', 'tasks.client_id')
            ->toBase();

        return FinancialSummary::filterByTask($query, $clientId, $projectId)
            ->groupBy('clients.id', 'clients.name', 'time_entries.currency', 'time_entries.hourly_rate')
            ->orderBy('clients.name')
            ->orderBy('time_entries.hourly_rate')
            ->select('clients.name', 'time_entries.currency', 'time_entries.hourly_rate')
            ->selectRaw('SUM(time_entries.duration_seconds)::bigint AS seconds, SUM(time_entries.amount)::bigint AS amount')
            ->selectRaw('MIN((time_entries.started_at AT TIME ZONE ?)::date)::text AS first_day, MAX((time_entries.started_at AT TIME ZONE ?)::date)::text AS last_day', [$tz, $tz])
            ->get()
            ->map(fn (object $row): RateLine => new RateLine(
                client: (string) $row->name,
                currency: Currency::from((string) $row->currency),
                rate: (int) $row->hourly_rate,
                seconds: (int) $row->seconds,
                amount: (int) $row->amount,
                from: CarbonImmutable::parse((string) $row->first_day),
                to: CarbonImmutable::parse((string) $row->last_day),
            ))
            ->values()
            ->all();
    }
}
