<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\RecurringTask;

/**
 * @template TModel of RecurringTask
 *
 * @extends ArchivableBuilder<TModel>
 */
final class RecurringTaskBuilder extends ArchivableBuilder
{
    /** Templates that still create tasks, so neither the template nor its client is archived. */
    public function generating(): static
    {
        return $this->active()->whereHas('client', fn (ArchivableBuilder $client) => $client->active());
    }
}
