<?php

declare(strict_types=1);

namespace App\Filament\RelationManagers;

use App\Models\Client;
use App\Models\Link;
use App\Models\Task;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Links');
    }

    public function form(Schema $schema): Schema
    {
        $owner = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('url')
                ->label(__('URL'))
                ->placeholder('https://')
                // Only http(s), like the database check: no javascript: or data: links.
                ->rules(['url:http,https'])
                ->maxLength(2048)
                ->required()
                ->columnSpanFull(),
            TextInput::make('title')->label(__('Title'))->placeholder(__('E.g. Contract, Staging'))->maxLength(255),
            Select::make('project_id')
                ->label(__('Project'))
                ->options(fn (): array => $owner instanceof Client ? $owner->projects()->orderBy('name')->pluck('name', 'id')->all() : [])
                ->visible($owner instanceof Client),
        ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'task.client']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Link'))
                    ->state(fn (Link $record): string => $record->label())
                    ->icon(Heroicon::OutlinedLink)
                    ->weight('medium')
                    ->url(fn (Link $record): string => $record->url, shouldOpenInNewTab: true)
                    ->description(fn (Link $record): string => collect([filled($record->title) ? $record->host() : null, $owner instanceof Task ? null : $record->subjectLabel()])->filter()->implode(' · '))
                    ->searchable(['title', 'url']),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add link'))
                    ->modalHeading(__('Add link')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No links yet'));
    }
}
