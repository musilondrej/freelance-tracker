<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Pages;

use App\Filament\Resources\Statements\StatementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStatements extends ListRecords
{
    protected static string $resource = StatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New draft')),
        ];
    }
}
