<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Tables;

use App\Enums\StatementStatus;
use App\Filament\Resources\Statements\StatementResource;
use App\Models\Statement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StatementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('client')->withSum('items as total', 'amount')->withCount('items'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label(__('Statement'))
                    ->state(fn (Statement $record): string => StatementResource::title($record))
                    ->description(fn (Statement $record): string => trans_choice(':count item|:count items', (int) $record->getAttribute('items_count')))
                    ->weight('medium'),
                TextColumn::make('client.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable()
                    ->hidden(fn (mixed $livewire): bool => $livewire instanceof RelationManager),
                TextColumn::make('status')->label(__('Status'))->badge()->sortable(),
                TextColumn::make('total')
                    ->label(__('Total (excl. VAT)'))
                    ->money(fn (Statement $record) => $record->currency, divideBy: 100, decimalPlaces: 2)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('sent_on')->label(__('Sent'))->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(StatementStatus::class)->multiple(),
                SelectFilter::make('client')->label(__('Client'))->relationship('client', 'name')->searchable()->preload()
                    ->hidden(fn (mixed $livewire): bool => $livewire instanceof RelationManager),
            ])
            ->emptyStateHeading(__('No statements'))
            ->emptyStateDescription(__('Create one from the "To invoice" list or add time entries to a draft.'));
    }
}
