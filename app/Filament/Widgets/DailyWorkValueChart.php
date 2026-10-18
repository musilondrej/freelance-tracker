<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PricingType;
use App\Reporting\DailyWorkValue;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Illuminate\Support\Js;

/** The tracked time chart's frame with work value in CZK, so fixed-price tasks show up too. */
class DailyWorkValueChart extends DailyTrackedTimeChart
{
    public function getHeading(): string
    {
        return __('Work value per day');
    }

    public function getDescription(): string
    {
        return $this->getCachedData() === [] ? __('Shown once the first ČNB rate is fetched') : parent::getDescription();
    }

    protected function getData(): array
    {
        [$period, $days] = $this->lastDays();
        $values = DailyWorkValue::inCzk($period);

        if ($values === null) {
            return [];
        }

        return [
            'labels' => $days->map(fn (CarbonImmutable $day): string => $day->format(Period::SHORT_DATE_FORMAT))->all(),
            'datasets' => collect([PricingType::Hourly, PricingType::Fixed])->map(fn (PricingType $type): array => [
                'label' => $type->getLabel(),
                // Koruny for the axis.
                'data' => $days->map(fn (CarbonImmutable $day): float => ($values[$type->value][$day->toDateString()] ?? 0) / 100)->all(),
                'backgroundColor' => $type->chartColor(),
                'borderColor' => 'transparent',
                'borderRadius' => 4,
                'maxBarThickness' => 24,
            ])->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        $total = Js::from(__('Total').': ');

        return RawJs::make(<<<JS
            (() => {
                const money = new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK', maximumFractionDigits: 0 });

                return {
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        mode: 'index',
                        filter: (item) => item.parsed.y > 0,
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + money.format(context.parsed.y),
                            footer: (items) => items.length > 1 ? {$total} + money.format(items.reduce((sum, item) => sum + item.parsed.y, 0)) : '',
                        },
                    },
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { callback: (value) => money.format(value) },
                        grid: { color: 'rgba(128, 128, 128, 0.15)' },
                    },
                },
                };
            })()
        JS);
    }
}
