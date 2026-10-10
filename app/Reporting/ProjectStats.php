<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\BudgetStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Support\Money;
use App\Support\Period;

/**
 * All-time overview of a project. Budget consumed and planned come from ProjectBuilder::withBudgetUsage().
 */
final readonly class ProjectStats
{
    public const int BUDGET_WARNING_PERCENT = 80;

    public function __construct(
        public int $openTaskCount,
        public int $doneTaskCount,
        public ?int $estimatedSeconds,
        public int $trackedSeconds,
        public CurrencyTotals $totals,
        public Project $project,
    ) {}

    /** Whole percent of the budget consumed, rounded half up. Null without a budget. */
    public static function usagePercent(int $consumed, ?int $budget): ?int
    {
        return $budget === null || $budget === 0 ? null : Money::roundHalfUpDiv($consumed * 100, $budget);
    }

    /**
     * Over: consumed or planned work is above the budget. Near: uses the rounded percent,
     * so the colour matches the shown number. Null without a budget.
     */
    public static function status(int $consumed, int $planned, ?int $budget): ?BudgetStatus
    {
        return match (true) {
            $budget === null => null,
            $consumed > $budget || $planned > $budget => BudgetStatus::Over,
            (self::usagePercent($consumed, $budget) ?? 0) >= self::BUDGET_WARNING_PERCENT => BudgetStatus::Near,
            default => BudgetStatus::Ok,
        };
    }

    public static function for(Project $project): self
    {
        $project = Project::query()->with('client')->withBudgetUsage()->findOrFail($project->id);
        $currency = $project->client->currency;

        $tasks = $project->tasks()
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS done', [TaskStatus::Done->value])
            ->selectRaw('SUM(estimated_seconds)::bigint AS estimated')
            ->first();

        $total = (int) ($tasks->total ?? 0);
        $done = (int) ($tasks->done ?? 0);

        $totals = FinancialSummary::for(Period::allTime(), projectId: $project->id)[$currency->value]
            ?? new CurrencyTotals($currency);

        return new self(
            openTaskCount: $total - $done,
            doneTaskCount: $done,
            estimatedSeconds: $project->estimated_seconds ?? (isset($tasks->estimated) ? (int) $tasks->estimated : null),
            trackedSeconds: $totals->trackedSeconds(),
            totals: $totals,
            project: $project,
        );
    }
}
