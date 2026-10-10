<?php

declare(strict_types=1);

namespace App\Filament\Resources\TimeEntries;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\HasSentenceCaseLabels;
use App\Filament\Resources\TimeEntries\Pages\ManageTimeEntries;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Filament\Resources\TimeEntries\Tables\TimeEntriesTable;
use App\Models\TimeEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TimeEntryResource extends Resource
{
    use HasSentenceCaseLabels;

    protected static ?string $model = TimeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Work;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('time entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('time entries');
    }

    public static function getNavigationLabel(): string
    {
        return __('Time tracking');
    }

    public static function form(Schema $schema): Schema
    {
        return TimeEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TimeEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTimeEntries::route('/'),
        ];
    }
}
