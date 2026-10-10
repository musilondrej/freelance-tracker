<?php

declare(strict_types=1);

namespace App\Filament\RelationManagers;

use App\Filament\Forms\MarkdownField;
use App\Models\Client;
use App\Models\Note;
use App\Models\Task;
use App\Support\Period;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dated notes of a client, shared by the client, project and task pages. Each page shows its own notes.
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Notes');
    }

    public function form(Schema $schema): Schema
    {
        $owner = $this->getOwnerRecord();

        return $schema->components([
            DatePicker::make('noted_on')->label(__('Date'))->default(Period::localToday())->required(),
            Select::make('project_id')
                ->label(__('Project'))
                ->options(fn (): array => $owner instanceof Client ? $owner->projects()->orderBy('name')->pluck('name', 'id')->all() : [])
                ->visible($owner instanceof Client),
            MarkdownField::make('body')
                ->label(__('Note'))
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'task.client']))
            ->defaultSort(fn (Builder $query) => $query->latest('noted_on')->orderByDesc('id'))
            ->columns([
                TextColumn::make('noted_on')->label(__('Date'))->date()->sortable()->width('1%')->alignStart(),
                TextColumn::make('body')
                    ->label(__('Note'))
                    ->markdown()
                    ->wrap()
                    ->searchable()
                    ->description(fn (Note $record): ?string => $owner instanceof Task ? null : $record->subjectLabel()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add note'))
                    ->modalHeading(__('Add note'))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        // The relationship sets the owner's key; a note always needs its client too.
                        'client_id' => $owner instanceof Client ? $owner->id : $owner->getAttribute('client_id'),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No notes yet'));
    }
}
