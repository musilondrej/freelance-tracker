<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\RelationManagers;

use App\Filament\Actions\StatementActions;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Builders\StatementItemBuilder;
use App\Models\Statement;
use App\Models\StatementItem;
use App\Support\Duration;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Items');
    }

    private function statement(): Statement
    {
        /** @var Statement */
        return $this->getOwnerRecord();
    }

    private function refreshStatement(): void
    {
        $this->dispatch('refresh-statement');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('description')->label(__('Description'))->required()->maxLength(255)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $currency = $this->statement()->currency;
        $editable = fn (): bool => $this->statement()->isDraft();

        return $table
            ->modifyQueryUsing(fn (StatementItemBuilder $query) => $query->with('task'))
            ->modelLabel(__('item'))
            ->pluralModelLabel(__('items'))
            ->paginated(false)
            ->columns([
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->wrap()
                    ->description(fn (StatementItem $record): string => $record->pricing_type->getLabel()),
                TextColumn::make('task.title')
                    ->label(__('Task'))
                    ->url(fn (StatementItem $record): string => TaskResource::getUrl('view', ['record' => $record->task_id]))
                    ->toggleable(),
                TextColumn::make('billed_seconds')
                    ->label(__('Time'))
                    ->formatStateUsing(fn (int $state): ?string => $state > 0 ? Duration::format($state).' h' : null)
                    ->alignEnd(),
                TextColumn::make('unit_price')
                    ->label(__('Rate'))
                    ->money($currency, divideBy: 100, decimalPlaces: 2)
                    ->alignEnd(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money($currency, divideBy: 100, decimalPlaces: 2)
                    ->alignEnd()
                    ->summarize(Sum::make()->label(__('Total'))->money($currency, divideBy: 100, decimalPlaces: 2)),
            ])
            ->headerActions([
                StatementActions::addTasks()->record($this->statement())->after($this->refreshStatement(...)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->visible($editable),
                    DeleteAction::make()->visible($editable)
                        ->modalDescription(__('The work of this item becomes billable again.'))
                        ->after($this->refreshStatement(...)),
                ]),
            ])
            ->emptyStateHeading(__('No items'))
            ->emptyStateDescription(__('Add tasks with unbilled work, or select time entries in Time tracking.'));
    }
}
