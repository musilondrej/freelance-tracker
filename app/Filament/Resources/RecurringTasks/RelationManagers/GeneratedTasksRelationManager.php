<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTasks\RelationManagers;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GeneratedTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $relatedResource = TaskResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Generated tasks');
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('period_start', 'desc');
    }
}
