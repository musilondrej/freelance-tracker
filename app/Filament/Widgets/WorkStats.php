<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Duration;
use App\Support\Period;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkStats extends StatsOverviewWidget
{
    use RefreshesOnTimerChange;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $hours = fn (Period $period): string => Duration::format((int) TimeEntry::query()->startedIn($period)->sum('duration_seconds')).' h';
        $workday = User::workdayHours();

        return [
            Stat::make(__('Today'), $hours(Period::today()))
                ->description(__('of :hours h', ['hours' => $workday]))
                ->icon(Heroicon::OutlinedClock),
            Stat::make(__('This week'), $hours(Period::thisWeek()))
                ->description(__('of :hours h', ['hours' => User::WORKDAYS_PER_WEEK * $workday]))
                ->icon(Heroicon::OutlinedCalendarDays),
            Stat::make(__('This month'), $hours(Period::thisMonth()))
                ->description(__('of :hours h', ['hours' => self::workdaysThisMonth() * $workday]))
                ->icon(Heroicon::OutlinedCalendar),
        ];
    }

    /** Monday to Friday in the current local month; public holidays are not subtracted. */
    private static function workdaysThisMonth(): int
    {
        $month = Period::localToday();

        return collect(range(1, $month->daysInMonth))
            ->filter(fn (int $day): bool => $month->setDay($day)->isWeekday())
            ->count();
    }
}
