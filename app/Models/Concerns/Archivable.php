<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;

/**
 * Archived records are hidden from lists but stay in reports.
 * Records with history are never deleted (foreign keys use RESTRICT).
 *
 * @property CarbonImmutable|null $archived_at
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'immutable_datetime']);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->forceFill(['archived_at' => now()])->save();
    }

    public function unarchive(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }
}
