<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reports;

use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Reporting\RateLine;
use App\Reporting\ReportFilters;
use App\Support\Duration;
use App\Support\Money;
use App\Support\Period;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class ReportRates extends TableWidget
{
    use InteractsWithPageFilters;
    use RefreshesOnTimerChange;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Historical rates'))
            ->description(__('Hourly rates frozen on time entries in the period.'))
            ->records(fn (): array => collect(ReportFilters::from($this->pageFilters)->rates())
                ->mapWithKeys(fn (RateLine $line, int $i): array => [$i => ['line' => $line, 'client' => $line->client]])
                ->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('client')->label(__('Client'))->weight('medium'),
                TextColumn::make('rate')->label(__('Rate'))->alignEnd()
                    ->state(fn (array $record): string => Money::format($record['line']->rate, $record['line']->currency).'/h'),
                TextColumn::make('hours')->label(__('Hours'))->alignEnd()
                    ->state(fn (array $record): string => Duration::format($record['line']->seconds)),
                TextColumn::make('value')->label(__('Value'))->alignEnd()
                    ->state(fn (array $record): string => Money::format($record['line']->amount, $record['line']->currency)),
                TextColumn::make('used')->label(__('Used'))->alignEnd()->color('gray')
                    ->state(fn (array $record): string => $record['line']->from->format(Period::SHORT_DATE_FORMAT).' – '.$record['line']->to->format(Period::DATE_FORMAT)),
            ])
            ->emptyStateHeading(__('No hourly work in the period.'));
    }
}
