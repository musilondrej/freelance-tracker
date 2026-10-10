<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PricingType;
use App\Exceptions\DomainRuleViolation;
use App\Models\Builders\TimeEntryBuilder;
use Carbon\CarbonImmutable;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Refreshes;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Work on a task. `ended_at = null` means the timer is running.
 *
 * The snapshot (pricing_type, hourly_rate, currency) is set when the entry is finished or moved
 * to another task. Later rate changes do not change it. The database computes `duration_seconds` and `amount`.
 *
 * @property int $id
 * @property int $task_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property string|null $note
 * @property PricingType|null $pricing_type
 * @property int|null $hourly_rate
 * @property Currency|null $currency
 * @property-read int|null $duration_seconds
 * @property-read int|null $amount Value of work in minor units (0 for fixed and non-billable).
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Task $task
 */
#[Fillable(['task_id', 'started_at', 'ended_at', 'note'])]
#[Refreshes(['duration_seconds', 'amount'])]
#[UseEloquentBuilder(TimeEntryBuilder::class)]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (TimeEntry $entry): void {
            // timestamptz(0) rounds sub-seconds. Cut them off so durations match what we compute.
            $entry->started_at = $entry->started_at->startOfSecond();
            $entry->ended_at = $entry->ended_at?->startOfSecond();

            if ($entry->ended_at === null) {
                if ($entry->exists && $entry->getOriginal('ended_at') !== null) {
                    throw DomainRuleViolation::because('A finished time entry cannot be resumed.');
                }

                return;
            }

            $justFinished = ! $entry->exists || $entry->getOriginal('ended_at') === null;

            if ($justFinished || $entry->isDirty('task_id')) {
                $entry->forceFill(Task::query()->findOrFail($entry->task_id)->timeEntrySnapshot());
            }
        });

        static::updating(function (TimeEntry $entry): void {
            if ($entry->isAllocated()) {
                throw DomainRuleViolation::because('A time entry on a statement cannot be changed.');
            }
        });

        static::deleting(function (TimeEntry $entry): void {
            if ($entry->isAllocated()) {
                throw DomainRuleViolation::because('A time entry on a statement cannot be deleted.');
            }
        });
    }

    /** Seconds since the start. For a finished entry use `duration_seconds`. */
    public function elapsedSeconds(): int
    {
        return max(0, (int) $this->started_at->diffInSeconds(now()));
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function isAllocated(): bool
    {
        return $this->statementItems()->exists();
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsToMany<StatementItem, $this> */
    public function statementItems(): BelongsToMany
    {
        return $this->belongsToMany(StatementItem::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'pricing_type' => PricingType::class,
            'hourly_rate' => 'integer',
            'currency' => Currency::class,
            'duration_seconds' => 'integer',
            'amount' => 'integer',
        ];
    }
}
