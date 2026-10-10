<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingTiming;
use App\Enums\PricingType;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Exceptions\DomainRuleViolation;
use App\Models\Builders\RecurringTaskBuilder;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasContextLabel;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template that creates one task per period, like monthly website maintenance.
 * Archiving stops it. Created tasks are normal tasks.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
 * @property string $title
 * @property string|null $description
 * @property TaskPriority $priority
 * @property PricingType $pricing_type
 * @property int|null $hourly_rate
 * @property int|null $fixed_price
 * @property int|null $estimated_seconds
 * @property RecurrenceFrequency $frequency
 * @property int $interval Repeat every N frequency units, like every 2 months.
 * @property BillingTiming $billing_timing
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property int $periods_generated
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 * @property-read Project|null $project
 */
#[Fillable([
    'client_id', 'project_id', 'title', 'description', 'priority', 'pricing_type', 'hourly_rate', 'fixed_price',
    'estimated_seconds', 'frequency', 'interval', 'billing_timing', 'starts_on', 'ends_on',
])]
#[UseEloquentBuilder(RecurringTaskBuilder::class)]
class RecurringTask extends Model
{
    use Archivable;
    use HasContextLabel;

    /** @use HasFactory<RecurringTaskFactory> */
    use HasFactory;

    protected $attributes = [
        'priority' => TaskPriority::Normal->value,
        'pricing_type' => PricingType::Fixed->value,
        'interval' => 1,
        'billing_timing' => BillingTiming::InArrears->value,
        'periods_generated' => 0,
    ];

    protected static function booted(): void
    {
        static::updating(function (RecurringTask $recurring): void {
            if ($recurring->periods_generated > 0 && $recurring->isDirty(['starts_on', 'frequency', 'interval', 'client_id', 'project_id'])) {
                throw DomainRuleViolation::because('The schedule and client cannot be changed once tasks were generated. Archive it and create a new one.');
            }
        });
    }

    /** First day of period k, counted from 0. Always computed from starts_on, so schedules on the 31st do not drift. */
    public function periodStart(int $k): CarbonImmutable
    {
        return $this->starts_on->addMonthsNoOverflow($k * $this->frequency->months() * $this->interval);
    }

    /** Last day of period k, inclusive. */
    public function periodEnd(int $k): CarbonImmutable
    {
        return $this->periodStart($k + 1)->subDay();
    }

    /** Start of the next period to generate, or null when the schedule has ended. */
    public function nextPeriodStart(): ?CarbonImmutable
    {
        $start = $this->periodStart($this->periods_generated);

        return $this->ends_on !== null && $start->greaterThan($this->ends_on) ? null : $start;
    }

    /** "Monthly", or "Every 2 months" for longer intervals. */
    public function scheduleLabel(): string
    {
        $months = $this->interval * $this->frequency->months();

        return $this->interval === 1 ? $this->frequency->getLabel() : trans_choice('Every :count months', $months, ['count' => $months]);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'pricing_type' => PricingType::class,
            'hourly_rate' => 'integer',
            'fixed_price' => 'integer',
            'estimated_seconds' => 'integer',
            'frequency' => RecurrenceFrequency::class,
            'interval' => 'integer',
            'billing_timing' => BillingTiming::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'periods_generated' => 'integer',
        ];
    }
}
