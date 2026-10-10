<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTasks;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\HasSentenceCaseLabels;
use App\Filament\Resources\RecurringTasks\Pages\CreateRecurringTask;
use App\Filament\Resources\RecurringTasks\Pages\EditRecurringTask;
use App\Filament\Resources\RecurringTasks\Pages\ListRecurringTasks;
use App\Filament\Resources\RecurringTasks\RelationManagers\GeneratedTasksRelationManager;
use App\Filament\Resources\RecurringTasks\Schemas\RecurringTaskForm;
use App\Filament\Resources\RecurringTasks\Tables\RecurringTasksTable;
use App\Models\RecurringTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RecurringTaskResource extends Resource
{
    use HasSentenceCaseLabels;

    protected static ?string $model = RecurringTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Engagements;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('recurring task');
    }

    public static function getPluralModelLabel(): string
    {
        return __('recurring tasks');
    }

    public static function getNavigationLabel(): string
    {
        return __('Recurring');
    }

    public static function getBreadcrumb(): string
    {
        return __('Recurring');
    }

    public static function form(Schema $schema): Schema
    {
        return RecurringTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringTasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GeneratedTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringTasks::route('/'),
            'create' => CreateRecurringTask::route('/create'),
            'edit' => EditRecurringTask::route('/{record}/edit'),
        ];
    }
}
