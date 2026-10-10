<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Project;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $relatedResource = TaskResource::class;

    public function table(Table $table): Table
    {
        /** @var Project $project */
        $project = $this->getOwnerRecord();

        return $table->headerActions([
            CreateAction::make()->url(TaskResource::getUrl('create', ['client' => $project->client_id, 'project' => $project->id])),
        ]);
    }
}
