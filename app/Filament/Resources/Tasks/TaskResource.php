<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks;

use App\Enums\NavigationGroup;
use App\Filament\RelationManagers\LinksRelationManager;
use App\Filament\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Concerns\HasSentenceCaseLabels;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\RelationManagers\ChecklistItemsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Schemas\TaskInfolist;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Builders\TaskBuilder;
use App\Models\Task;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TaskResource extends Resource
{
    use HasSentenceCaseLabels;

    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Work;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('task');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tasks');
    }

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ChecklistItemsRelationManager::class,
            TimeEntriesRelationManager::class,
            NotesRelationManager::class,
            LinksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }

    /** "ACME-12 Login form" in titles, breadcrumbs and global search. */
    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Task ? $record->key().' '.$record->title : static::getModelLabel();
    }

    /**
     * A key like ACME-12 finds that task. Anything else searches titles.
     *
     * @param  Builder<Model>  $query
     */
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        if ($query instanceof TaskBuilder && Task::parseKey($search) !== null) {
            $query->withKey($search);

            return;
        }

        parent::applyGlobalSearchAttributeConstraints($query, $search);
    }

    /** @return Builder<Model> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['client', 'project']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Task $record */
        return array_filter([
            __('Client') => $record->client->name,
            __('Project') => $record->project?->name,
            __('Status') => $record->status->getLabel(),
        ]);
    }
}
