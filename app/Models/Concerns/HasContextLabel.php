<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Client;
use App\Models\Project;

/**
 * "Client · Project" under a task or recurring template in lists.
 *
 * @property-read Client $client
 * @property-read Project|null $project
 */
trait HasContextLabel
{
    public function contextLabel(): string
    {
        return collect([$this->client->name, $this->project?->name])->filter()->implode(' · ');
    }
}
