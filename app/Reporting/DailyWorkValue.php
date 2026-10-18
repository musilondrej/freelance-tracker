<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\PricingType;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\Period;

/**
 * Work value per local day as one approximate CZK figure, for the dashboard chart. Same definition
 * as the work value in FinancialSummary: hourly entries by their start, fixed prices by the day
 * they became ready to bill.
 */
final class DailyWorkValue
{
    /**
     * @return array<string, array<string, int>>|null pricing type, then local date like "2026-10-11", then CZK minor units.
     *                                                Days without value are missing. Null before the first ČNB rate of a used currency.
     */
    public static function inCzk(Period $period): ?array
    {
        $queries = [
            PricingType::Hourly->value => TimeEntry::query()
                ->startedIn($period)
                ->hourly()
                ->toBase()
                ->selectRaw('(time_entries.started_at AT TIME ZONE ?)::date::text AS day, time_entries.currency, SUM(time_entries.amount)::bigint AS total', [Period::timezone()])
                ->groupBy('day', 'time_entries.currency'),
            PricingType::Fixed->value => Task::query()
                ->readyFixedIn($period)
                ->join('clients', 'clients.id', '=', 'tasks.client_id')
                ->toBase()
                ->selectRaw('(tasks.ready_to_bill_at AT TIME ZONE ?)::date::text AS day, clients.currency, SUM(tasks.fixed_price)::bigint AS total', [Period::timezone()])
                ->groupBy('day', 'clients.currency'),
        ];

        $result = [];

        foreach ($queries as $type => $query) {
            $amounts = [];

            foreach ($query->get() as $row) {
                $amounts[(string) $row->day][(string) $row->currency] = (int) $row->total;
            }

            $result[$type] = [];

            foreach ($amounts as $day => $byCurrency) {
                $czk = CzkTotal::of($byCurrency);

                if (! $czk instanceof CzkTotal) {
                    return null;
                }

                $result[$type][$day] = $czk->amount;
            }
        }

        return $result;
    }
}
