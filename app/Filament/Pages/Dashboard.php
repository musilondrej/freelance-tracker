<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveProjects;
use App\Filament\Widgets\ActiveTimer;
use App\Filament\Widgets\DailyTrackedTimeChart;
use App\Filament\Widgets\DailyWorkValueChart;
use App\Filament\Widgets\FinanceOverview;
use App\Filament\Widgets\RecentTimeEntries;
use App\Filament\Widgets\UpcomingDeadlines;
use App\Filament\Widgets\WorkStats;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            ActiveTimer::class,
            WorkStats::class,
            FinanceOverview::class,
            DailyTrackedTimeChart::class,
            DailyWorkValueChart::class,
            RecentTimeEntries::class,
            UpcomingDeadlines::class,
            ActiveProjects::class,
        ];
    }
}
