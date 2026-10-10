<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Statements\StatementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class StatementsRelationManager extends RelationManager
{
    protected static string $relationship = 'statements';

    protected static ?string $relatedResource = StatementResource::class;

    public function table(Table $table): Table
    {
        return $table->headerActions([
            CreateAction::make()->label(__('New draft'))->url(StatementResource::getUrl('create', ['client' => $this->getOwnerRecord()->getKey()])),
        ]);
    }
}
