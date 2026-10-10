<?php

declare(strict_types=1);

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Queries of archivable models (see App\Models\Concerns\Archivable).
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ArchivableBuilder extends Builder
{
    public function active(): static
    {
        return $this->whereNull($this->qualifyColumn('archived_at'));
    }
}
