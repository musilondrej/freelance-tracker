<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PricingType;
use App\Exceptions\DomainRuleViolation;
use App\Models\Builders\StatementItemBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A line of a statement. Its amount never changes later.
 * Hourly items sum their entries, fixed items hold a part of the fixed price.
 *
 * @property int $id
 * @property int $statement_id
 * @property int $task_id
 * @property string $description
 * @property PricingType $pricing_type
 * @property int|null $unit_price Hourly rate of the entries. Only for hourly items.
 * @property int $billed_seconds
 * @property int $amount
 * @property int $position
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Statement $statement
 * @property-read Task $task
 */
#[Fillable(['description', 'position'])]
#[UseEloquentBuilder(StatementItemBuilder::class)]
class StatementItem extends Model
{
    protected static function booted(): void
    {
        $guard = function (StatementItem $item): void {
            if (! $item->statement->isDraft()) {
                throw DomainRuleViolation::because('Items can only be changed on a draft statement.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Statement, $this> */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsToMany<TimeEntry, $this> */
    public function timeEntries(): BelongsToMany
    {
        return $this->belongsToMany(TimeEntry::class);
    }

    protected function casts(): array
    {
        return [
            'pricing_type' => PricingType::class,
            'unit_price' => 'integer',
            'billed_seconds' => 'integer',
            'amount' => 'integer',
            'position' => 'integer',
        ];
    }
}
