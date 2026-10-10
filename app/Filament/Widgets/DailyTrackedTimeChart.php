<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Currency;
use App\Models\User;
use App\Reporting\DailyTrackedTime;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;

class DailyTrackedTimeChart extends ChartWidget
{
    public const int DAYS = 30;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('Tracked time per day');
    }

    public function getDescription(): string
    {
        return __('Last :count days', ['count' => self::DAYS]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $today = Period::localToday();
        $from = $today->subDays(self::DAYS - 1);
        $days = Collection::times(self::DAYS, fn (int $day): CarbonImmutable => $from->addDays($day - 1));
        $seconds = DailyTrackedTime::for(Period::between($from->toDateString(), $today->toDateString()));

        return [
            'labels' => $days->map(fn (CarbonImmutable $day): string => $day->format(Period::SHORT_DATE_FORMAT))->all(),
            'datasets' => collect($seconds)->map(fn (array $byDay, string $code): array => [
                'label' => __('Clients in :currency', ['currency' => $code]),
                // Hours for the axis; the tooltip shows H:MM.
                'data' => $days->map(fn (CarbonImmutable $day): float => ($byDay[$day->toDateString()] ?? 0) / 3600)->all(),
                'backgroundColor' => Currency::from($code)->chartColor(),
                'borderColor' => 'transparent',
                'borderRadius' => 4,
                'maxBarThickness' => 24,
            ])->values()->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        $legend = count($this->getCachedData()['datasets']) > 1 ? 'true' : 'false';
        $total = Js::from(__('Total').': ');
        $workday = User::workdayHours();

        // H:MM like Duration::format(), from exact hours.
        return RawJs::make(<<<JS
            (() => {
                const hours = (value) => {
                    const minutes = Math.floor(Math.round(value * 3600) / 60);

                    return Math.floor(minutes / 60) + ':' + String(minutes % 60).padStart(2, '0') + ' h';
                };

                return {
                plugins: {
                    legend: { display: {$legend}, position: 'bottom' },
                    tooltip: {
                        mode: 'index',
                        filter: (item) => item.parsed.y > 0,
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + hours(context.parsed.y),
                            footer: (items) => items.length > 1 ? {$total} + hours(items.reduce((sum, item) => sum + item.parsed.y, 0)) : '',
                        },
                    },
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        // Keep the workday line visible on short days, with a tick on it.
                        suggestedMax: {$workday} + 1,
                        ticks: {
                            stepSize: 2,
                            callback: (value) => value + ' h',
                            font: (context) => ({ weight: context.tick?.value === {$workday} ? 'bold' : 'normal' }),
                        },
                        grid: {
                            color: (context) => context.tick?.value === {$workday} ? 'rgba(128, 128, 128, 0.7)' : 'rgba(128, 128, 128, 0.15)',
                            lineWidth: (context) => context.tick?.value === {$workday} ? 2 : 1,
                        },
                    },
                },
                };
            })()
        JS);
    }
}
