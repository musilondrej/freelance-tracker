<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements\Pages;

use App\Actions\Billing\CreateStatement as CreateStatementAction;
use App\Filament\Resources\Statements\StatementResource;
use App\Models\Client;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStatement extends CreateRecord
{
    protected static string $resource = StatementResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateStatementAction::class)->handle(
            Client::query()->findOrFail((int) $data['client_id']),
            note: $data['note'] ?? null,
        );
    }
}
