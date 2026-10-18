<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Builders\TaskBuilder;
use App\Models\Project;
use App\Support\Period;

/**
 * All-time overview of a project. Budget consumed and planned come from ProjectBuilder::withBudgetUsage().
 */
final readonly class ProjectStats
{
    public function __construct(
        public int $openTaskCount,
        public int $doneTaskCount,
        public ?int $estimatedSeconds,
        public CurrencyTotals $totals,
        public Project $project,
    ) {}

    public static function for(Project $project): self
    {
        $project = Project::query()
            ->with('client')
            ->withBudgetUsage()
            ->withCount(['tasks', 'tasks as open_tasks_count' => fn (TaskBuilder $query) => $query->open()])
            ->withSum('tasks as tasks_estimated_seconds', 'estimated_seconds')
            ->findOrFail($project->id);
        $currency = $project->client->currency;
        $open = (int) $project->getAttribute('open_tasks_count');
        $estimated = $project->getAttribute('tasks_estimated_seconds');

        $totals = FinancialSummary::for(Period::allTime(), projectId: $project->id)[$currency->value]
            ?? new CurrencyTotals($currency);

        return new self(
            openTaskCount: $open,
            doneTaskCount: (int) $project->getAttribute('tasks_count') - $open,
            estimatedSeconds: $project->estimated_seconds ?? ($estimated === null ? null : (int) $estimated),
            totals: $totals,
            project: $project,
        );
    }
}
