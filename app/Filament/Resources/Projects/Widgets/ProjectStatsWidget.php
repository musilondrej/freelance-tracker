<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Widgets;

use App\Enums\BudgetStatus;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\Project;
use App\Reporting\ProjectStats;
use App\Support\Duration;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStatsWidget extends StatsOverviewWidget
{
    use RefreshesOnTimerChange;

    public ?Project $record = null;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        if (! $this->record instanceof Project) {
            return [];
        }

        $stats = ProjectStats::for($this->record);
        $project = $stats->project;
        $totals = $stats->totals;
        $money = fn (?int $amount): string => $amount === null ? '—' : $totals->format($amount);

        $budget = Stat::make(__('Budget'), $money($project->budget))
            ->icon(Heroicon::OutlinedChartPie);

        if ($project->budget !== null) {
            $budget = $budget
                ->description(__('Used :percent % · remaining :remaining', [
                    'percent' => $project->budgetUsagePercent() ?? 0,
                    'remaining' => $money($project->budgetRemaining()),
                ]))
                ->color($project->budgetStatus()?->getColor());
        }

        return [
            $budget,
            Stat::make(__('Work value'), $money($project->budgetConsumed()))
                ->description(__('Planned incl. unfinished fixed tasks: :amount', ['amount' => $money($project->budgetPlanned())]))
                ->color($project->budgetStatus() === BudgetStatus::Over ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedBriefcase),
            Stat::make(__('Invoiced'), $money($totals->billed))
                ->description(__('To invoice: :amount', ['amount' => $money($totals->unbilled)]))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make(__('Time'), Duration::format($stats->trackedSeconds).' h')
                ->description(($stats->estimatedSeconds !== null ? __('Estimate: :time h', ['time' => Duration::format($stats->estimatedSeconds)]).' · ' : '')
                    .__(':open open / :done done tasks', ['open' => $stats->openTaskCount, 'done' => $stats->doneTaskCount]))
                ->color($stats->estimatedSeconds !== null && $stats->trackedSeconds > $stats->estimatedSeconds ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedClock),
        ];
    }
}
