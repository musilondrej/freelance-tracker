<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ChecklistItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Checklist');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return Task::query()->withChecklistProgress()->whereKey($ownerRecord->getKey())->firstOrFail()->checklistProgress();
    }

    private function task(): Task
    {
        /** @var Task */
        return $this->getOwnerRecord();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('Item'))->required()->maxLength(255)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->paginated(false)
            ->modelLabel(__('item'))
            ->pluralModelLabel(__('items'))
            ->columns([
                ToggleColumn::make('is_done')->label(__('Done'))->width('1%'),
                TextInputColumn::make('title')->label(__('Item'))->rules(['required', 'max:255']),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add item'))
                    ->createAnother()
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'position' => (int) $this->task()->checklistItems()->max('position') + 1,
                    ]),
            ])
            ->recordActions([
                DeleteAction::make()->iconButton()->color('gray'),
            ])
            ->emptyStateHeading(__('No checklist items'))
            ->emptyStateDescription(null);
    }
}
