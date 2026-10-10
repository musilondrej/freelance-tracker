<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Actions\ArchiveActions;
use App\Filament\Tables\ArchivedFilter;
use App\Models\Builders\ArchivableBuilder;
use App\Models\Builders\TaskBuilder;
use App\Models\Client;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->description(fn (Client $record): ?string => $record->company_name)
                    ->searchable(['name', 'company_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('code')->label(__('Key'))->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('email')
                    ->label(__('E-mail'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('hourly_rate')
                    ->label(__('Hourly rate'))
                    ->formatStateUsing(fn (?int $state, Client $record): ?string => $state === null ? null : Money::format($state, $record->currency))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('projects_count')
                    ->label(__('Projects'))
                    ->counts(['projects' => fn (ArchivableBuilder $query) => $query->active()])
                    ->formatStateUsing(fn (int $state): ?string => $state > 0 ? (string) $state : null)
                    ->alignEnd(),
                TextColumn::make('tasks_count')
                    ->label(__('Open tasks'))
                    ->counts(['tasks' => fn (TaskBuilder $query) => $query->active()->open()])
                    ->formatStateUsing(fn (int $state): ?string => $state > 0 ? (string) $state : null)
                    ->alignEnd(),
                TextColumn::make('registration_number')
                    ->label(__('Company ID (IČO)'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ArchivedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ...ArchiveActions::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No clients yet'))
            ->emptyStateDescription(__('Create a client with a currency and a default hourly rate to start tracking work.'));
    }
}
