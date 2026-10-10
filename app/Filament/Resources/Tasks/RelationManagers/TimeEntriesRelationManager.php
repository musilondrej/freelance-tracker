<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    protected static ?string $relatedResource = TimeEntryResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Time entries');
    }

    public function table(Table $table): Table
    {
        return $table->headerActions([
            CreateAction::make()
                ->label(__('Log time'))
                ->mutateDataUsing(fn (array $data): array => TimeEntryForm::prepare($data)),
        ]);
    }
}
