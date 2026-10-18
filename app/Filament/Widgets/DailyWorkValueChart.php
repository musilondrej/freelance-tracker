<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PricingType;
use App\Reporting\DailyWorkValue;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Illuminate\Support\Js;

/**
 * The tracked time chart's frame with work value, so fixed-price tasks show up too.
 * One stack per currency, each on its own axis, since currencies are never added up.
 */
class DailyWorkValueChart extends DailyTrackedTimeChart
{
    public function getHeading(): string
    {
        return __('Work value per day');
    }

    protected function getData(): array
    {
        [$period, $days] = $this->lastDays();
        $values = DailyWorkValue::for($period);
        $several = count($values) > 1;

        return [
            'labels' => $days->map(fn (CarbonImmutable $day): string => $day->format(Period::SHORT_DATE_FORMAT))->all(),
            'datasets' => collect(array_keys($values))->flatMap(fn (string $code, int $index): array => collect([PricingType::Hourly, PricingType::Fixed])->map(fn (PricingType $type): array => [
                'label' => $several ? $type->getLabel().' · '.$code : $type->getLabel(),
                // Whole units for the axis.
                'data' => $days->map(fn (CarbonImmutable $day): float => ($values[$code][$type->value][$day->toDateString()] ?? 0) / 100)->all(),
                // The second currency is lighter; a third would look like the second.
                'backgroundColor' => $type->chartColor().($index === 0 ? '' : '80'),
                'borderColor' => 'transparent',
                'borderRadius' => 4,
                'maxBarThickness' => 24,
                'stack' => $code,
                'yAxisID' => $code,
            ])->all())->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        /** @var list<array{stack: string}> $datasets */
        $datasets = $this->getCachedData()['datasets'];
        $currencies = Js::from(array_values(array_unique(array_column($datasets, 'stack'))));
        $total = Js::from(__('Total').': ');

        return RawJs::make(<<<JS
            (() => {
                const currencies = {$currencies};
                const money = Object.fromEntries(currencies.map((code) => [code, new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: code, maximumFractionDigits: 0 })]));

                return {
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        mode: 'index',
                        filter: (item) => item.parsed.y > 0,
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + money[context.dataset.stack].format(context.parsed.y),
                            // One total per currency.
                            footer: (items) => {
                                if (items.length < 2) {
                                    return '';
                                }

                                const sums = {};
                                items.forEach((item) => sums[item.dataset.stack] = (sums[item.dataset.stack] ?? 0) + item.parsed.y);

                                return {$total} + Object.entries(sums).map(([code, sum]) => money[code].format(sum)).join(' + ');
                            },
                        },
                    },
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    // Chart.js adds a default y axis next to the currency axes.
                    y: { display: false },
                    ...Object.fromEntries(currencies.map((code, index) => [code, {
                        axis: 'y',
                        position: index === 0 ? 'left' : 'right',
                        stacked: true,
                        beginAtZero: true,
                        ticks: { callback: (value) => money[code].format(value) },
                        grid: index === 0 ? { color: 'rgba(128, 128, 128, 0.15)' } : { display: false },
                    }])),
                },
                };
            })()
        JS);
    }
}
