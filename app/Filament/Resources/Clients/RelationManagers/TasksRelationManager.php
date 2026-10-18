<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Tasks\TaskResource;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    use RefreshesOnTimerChange;

    protected static string $relationship = 'tasks';

    protected static ?string $relatedResource = TaskResource::class;

    public function table(Table $table): Table
    {
        return $table->headerActions([
            CreateAction::make()->url(TaskResource::getUrl('create', ['client' => $this->getOwnerRecord()->getKey()])),
        ]);
    }
}
