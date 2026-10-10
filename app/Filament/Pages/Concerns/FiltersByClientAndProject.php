<?php

declare(strict_types=1);

namespace App\Filament\Pages\Concerns;

use App\Models\Builders\ProjectBuilder;
use App\Models\Client;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

trait FiltersByClientAndProject
{
    #[Url(as: 'client')]
    public ?int $clientId = null;

    #[Url(as: 'project')]
    public ?int $projectId = null;

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function clients(): array
    {
        return Client::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function projects(): array
    {
        return Project::query()
            ->when($this->clientId, fn (ProjectBuilder $q, int $id) => $q->where('client_id', $id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedClientId(): void
    {
        $this->projectId = null;
    }
}
