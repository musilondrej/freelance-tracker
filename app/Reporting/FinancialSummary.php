<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\Currency;
use App\Enums\PricingType;
use App\Enums\StatementStatus;
use App\Models\Builders\TaskBuilder;
use App\Models\Builders\TimeEntryBuilder;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\Period;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Per-currency totals, summed in SQL from historical snapshots. Definitions: docs/billing.md.
 */
final class FinancialSummary
{
    /**
     * @return array<string, CurrencyTotals> keyed by currency code
     */
    public static function for(
        Period $period,
        ?int $clientId = null,
        ?int $projectId = null,
        ?PricingType $pricingType = null,
    ): array {
        return self::compute($period, $clientId, $projectId, $pricingType)[''] ?? [];
    }

    /**
     * The same totals per client or per project, one line per currency, without empty lines.
     * Sorted by currency, then by work value, highest first.
     *
     * @param  'client_id'|'project_id'  $dimension
     * @return array<int, NamedTotals>
     */
    public static function breakdown(
        string $dimension,
        Period $period,
        ?int $clientId = null,
        ?int $projectId = null,
        ?PricingType $pricingType = null,
    ): array {
        // The key '' means no project.
        $data = self::compute($period, $clientId, $projectId, $pricingType, 'tasks.'.$dimension);
        $ids = collect($data)->except([''])->keys();
        $names = $dimension === 'client_id'
            ? Client::query()->whereKey($ids)->pluck('name', 'id')
            : Project::query()->whereKey($ids)->pluck('name', 'id');

        return collect($data)
            ->flatMap(fn (array $byCurrency, string $key) => collect($byCurrency)
                ->reject(fn (CurrencyTotals $totals): bool => $totals->isEmpty())
                ->map(fn (CurrencyTotals $totals): NamedTotals => new NamedTotals(
                    $key === '' ? __('No project') : (string) ($names[(int) $key] ?? '#'.$key),
                    $totals,
                ))
                ->values())
            ->sortBy([
                fn (NamedTotals $a, NamedTotals $b): int => $a->totals->currency->value <=> $b->totals->currency->value,
                fn (NamedTotals $a, NamedTotals $b): int => $b->totals->workValue <=> $a->totals->workValue,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  literal-string|null  $groupColumn
     * @return array<string, array<string, CurrencyTotals>>
     */
    private static function compute(
        Period $period,
        ?int $clientId,
        ?int $projectId,
        ?PricingType $pricingType,
        ?string $groupColumn = null,
    ): array {
        $filter = fn (Builder $query): Builder => self::filterByTask($query, $clientId, $projectId, $pricingType);

        $amounts = fn (TimeEntryBuilder $query): array => self::sum($filter(self::withTask($query)), 'time_entries.currency', 'time_entries.amount', $groupColumn);
        $seconds = fn (TimeEntryBuilder $query): array => self::sum($filter(self::withTask($query)), 'time_entries.currency', 'time_entries.duration_seconds', $groupColumn);
        $fixedPrices = fn (TaskBuilder $query): array => self::sum($filter(self::withClient($query)), 'clients.currency', 'tasks.fixed_price', $groupColumn);
        $items = fn (Builder $query): array => self::sum($filter($query), 'statements.currency', 'statement_items.amount', $groupColumn);

        $rows = [
            'hourlyValue' => $amounts(TimeEntry::query()->startedIn($period)->hourly()),
            'fixedValue' => $fixedPrices(Task::query()->readyFixedIn($period)),
            'unbilledHourly' => $amounts(TimeEntry::query()->unbilledHourly()),
            'unbilledFixed' => $fixedPrices(Task::query()->unbilledFixed()),
            'inDrafts' => $items(self::items()->where('statements.status', StatementStatus::Draft->value)),
            'billed' => $items(self::items()->where('statements.status', StatementStatus::Sent->value)->whereBetween('statements.sent_on', [$period->fromDate(), $period->toDate()])),
            'billableSeconds' => $seconds(TimeEntry::query()->startedIn($period)->billable()),
            'nonBillableSeconds' => $seconds(TimeEntry::query()->startedIn($period)->nonBillable()),
        ];

        $keys = collect($rows)->flatMap(fn (array $byKey) => array_keys($byKey))->unique()->sort()->values();
        $result = [];

        foreach ($keys as $key) {
            $codes = collect($rows)->flatMap(fn (array $byKey) => array_keys($byKey[$key] ?? []))->unique()->sort()->values();

            foreach ($codes as $code) {
                $value = fn (string $metric): int => $rows[$metric][$key][$code] ?? 0;

                $result[$key][$code] = new CurrencyTotals(
                    currency: Currency::from($code),
                    hourlyValue: $value('hourlyValue'),
                    fixedValue: $value('fixedValue'),
                    unbilled: $value('unbilledHourly') + $value('unbilledFixed'),
                    inDrafts: $value('inDrafts'),
                    billed: $value('billed'),
                    billableSeconds: $value('billableSeconds'),
                    nonBillableSeconds: $value('nonBillableSeconds'),
                );
            }
        }

        return $result;
    }

    /**
     * The report filters, on a query that joins `tasks`. Shared with RateHistory.
     */
    public static function filterByTask(Builder $query, ?int $clientId, ?int $projectId, ?PricingType $pricingType = null): Builder
    {
        return $query
            ->when($clientId, fn (Builder $q) => $q->where('tasks.client_id', $clientId))
            ->when($projectId, fn (Builder $q) => $q->where('tasks.project_id', $projectId))
            ->when($pricingType, fn (Builder $q) => $q->where('tasks.pricing_type', $pricingType?->value));
    }

    /**
     * @param  TimeEntryBuilder<TimeEntry>  $entries
     */
    private static function withTask(TimeEntryBuilder $entries): Builder
    {
        return $entries->join('tasks', 'tasks.id', '=', 'time_entries.task_id')->toBase();
    }

    /**
     * @param  TaskBuilder<Task>  $tasks
     */
    private static function withClient(TaskBuilder $tasks): Builder
    {
        return $tasks->join('clients', 'clients.id', '=', 'tasks.client_id')->toBase();
    }

    private static function items(): Builder
    {
        return DB::table('statement_items')
            ->join('statements', 'statements.id', '=', 'statement_items.statement_id')
            ->join('tasks', 'tasks.id', '=', 'statement_items.task_id');
    }

    /**
     * @param  literal-string  $currencyColumn
     * @param  literal-string  $valueSql
     * @param  literal-string|null  $groupColumn
     * @return array<string, array<string, int>> group key, then currency, then total. The group key is '' without grouping.
     */
    private static function sum(Builder $query, string $currencyColumn, string $valueSql, ?string $groupColumn): array
    {
        $groupKey = $groupColumn === null ? "''" : "COALESCE({$groupColumn}::text, '')";

        // SUM(bigint) returns numeric in Postgres, so cast it.
        $rows = $query
            ->groupByRaw($groupColumn === null ? $currencyColumn : "{$groupColumn}, {$currencyColumn}")
            ->selectRaw("{$groupKey} AS group_key, {$currencyColumn} AS currency, COALESCE(SUM({$valueSql}), 0)::bigint AS total")
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row->group_key][(string) $row->currency] = (int) $row->total;
        }

        return $result;
    }
}
