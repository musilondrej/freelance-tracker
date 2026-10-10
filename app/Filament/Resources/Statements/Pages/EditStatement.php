<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Pages;

use App\Filament\Resources\Statements\StatementResource;
use Filament\Resources\Pages\EditRecord;

class EditStatement extends EditRecord
{
    protected static string $resource = StatementResource::class;
}
