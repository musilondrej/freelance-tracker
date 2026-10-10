<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reports;

use App\Reporting\NamedTotals;
use App\Reporting\ReportFilters;
use App\Support\Duration;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class ReportBreakdown extends TableWidget
{
    use InteractsWithPageFilters;

    /** @var 'client_id'|'project_id' */
    public string $dimension = 'client_id';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->dimension === 'client_id' ? __('By client') : __('By project'))
            ->records(fn (?string $sortColumn, ?string $sortDirection): array => $this->rows($sortColumn, $sortDirection))
            ->defaultSort('work_value', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('medium')->description(fn (array $record): string => $record['currency']),
                TextColumn::make('tracked')->label(__('Hours'))->formatStateUsing(fn (int $state): string => Duration::format($state))->alignEnd()->sortable(),
                TextColumn::make('work_value')->label(__('Work value'))->formatStateUsing(fn (int $state, array $record): string => Money::format($state, $record['totals']->currency))->alignEnd()->sortable(),
                TextColumn::make('real_rate')->label(__('Real rate'))->tooltip(__('Work value per tracked hour, non-billable time included'))
                    ->formatStateUsing(fn (?int $state, array $record): ?string => $state === null ? null : Money::format($state, $record['totals']->currency).'/h')->alignEnd(),
                TextColumn::make('billed')->label(__('Invoiced'))->formatStateUsing(fn (int $state, array $record): string => Money::format($state, $record['totals']->currency))->alignEnd()->sortable(),
                TextColumn::make('unbilled')->label(__('To invoice (now)'))->formatStateUsing(fn (int $state, array $record): string => Money::format($state, $record['totals']->currency))->alignEnd()->sortable(),
            ])
            ->emptyStateHeading(__('No data for the selected filters.'));
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(?string $sortColumn, ?string $sortDirection): array
    {
        $rows = collect(ReportFilters::from($this->pageFilters)->breakdown($this->dimension))
            ->mapWithKeys(fn (NamedTotals $line, int $i): array => ["{$i}-{$line->totals->currency->value}" => [
                'name' => $line->name,
                'currency' => $line->totals->currency->value,
                'totals' => $line->totals,
                'tracked' => $line->totals->trackedSeconds(),
                'work_value' => $line->totals->workValue,
                'real_rate' => $line->totals->realHourlyRate(),
                'billed' => $line->totals->billed,
                'unbilled' => $line->totals->unbilled,
            ]]);

        // Money of different currencies is never compared: sort within each currency.
        return $sortColumn === null ? $rows->all() : $rows
            ->sortBy([['currency', 'asc'], [$sortColumn, $sortDirection === 'desc' ? 'desc' : 'asc']])
            ->all();
    }
}
