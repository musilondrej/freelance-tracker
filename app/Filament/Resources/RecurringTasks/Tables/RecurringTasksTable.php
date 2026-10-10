<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTasks\Tables;

use App\Enums\PricingType;
use App\Filament\Actions\ArchiveActions;
use App\Filament\Tables\ArchivedFilter;
use App\Models\Builders\RecurringTaskBuilder;
use App\Models\RecurringTask;
use App\Support\Money;
use App\Support\Period;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecurringTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (RecurringTaskBuilder $query) => $query->with(['client', 'project'])->withCount('tasks'))
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (RecurringTask $record): string => $record->contextLabel()),
                TextColumn::make('frequency')
                    ->label(__('Repeats'))
                    ->formatStateUsing(fn (RecurringTask $record): string => $record->scheduleLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('price')
                    ->label(__('Price per period'))
                    ->state(fn (RecurringTask $record): string => match ($record->pricing_type) {
                        PricingType::Fixed => Money::format($record->fixed_price ?? 0, $record->client->currency),
                        default => $record->pricing_type->getLabel(),
                    })
                    ->description(fn (RecurringTask $record): ?string => $record->pricing_type === PricingType::Fixed ? $record->billing_timing->getLabel() : null)
                    ->alignEnd(),
                TextColumn::make('next')
                    ->label(__('Next period'))
                    ->state(fn (RecurringTask $record): ?string => $record->isArchived() ? null : $record->nextPeriodStart()?->format(Period::DATE_FORMAT))
                    ->placeholder(__('Ended')),
                TextColumn::make('tasks_count')->label(__('Generated'))->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('client')->label(__('Client'))->relationship('client', 'name')->searchable()->preload(),
                ArchivedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ...ArchiveActions::make(),
                    DeleteAction::make()->modalDescription(__('Generated tasks are kept.')),
                ]),
            ])
            ->emptyStateHeading(__('No recurring tasks'))
            ->emptyStateDescription(__('E.g. monthly maintenance or yearly hosting: a task is created for every period.'));
    }
}
