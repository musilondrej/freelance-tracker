<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\HasSentenceCaseLabels;
use App\Filament\Resources\Statements\Pages\CreateStatement;
use App\Filament\Resources\Statements\Pages\EditStatement;
use App\Filament\Resources\Statements\Pages\ListStatements;
use App\Filament\Resources\Statements\Pages\ViewStatement;
use App\Filament\Resources\Statements\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Statements\Schemas\StatementForm;
use App\Filament\Resources\Statements\Schemas\StatementInfolist;
use App\Filament\Resources\Statements\Tables\StatementsTable;
use App\Models\Statement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StatementResource extends Resource
{
    use HasSentenceCaseLabels;

    protected static ?string $model = Statement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('statement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('statements');
    }

    public static function form(Schema $schema): Schema
    {
        return StatementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StatementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StatementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatements::route('/'),
            'create' => CreateStatement::route('/create'),
            'view' => ViewStatement::route('/{record}'),
            'edit' => EditStatement::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Statement ? self::title($record) : parent::getRecordTitle($record);
    }

    public static function title(Statement $statement): string
    {
        return __('Statement #:id', ['id' => $statement->id]);
    }
}
