<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PricingType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Builders\TaskBuilder;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\HasContextLabel;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $client_id
 * @property int $number Per-client number, part of the key ACME-12.
 * @property int|null $project_id
 * @property int|null $recurring_task_id
 * @property CarbonImmutable|null $period_start First day of the recurring period this task covers.
 * @property CarbonImmutable|null $period_end Last day of the recurring period (inclusive).
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property PricingType $pricing_type
 * @property int|null $hourly_rate Overrides the project or client rate. Minor units per hour.
 * @property int|null $fixed_price Minor units. Required for fixed pricing.
 * @property int|null $estimated_seconds
 * @property CarbonImmutable|null $due_on
 * @property int $position Order within the kanban column.
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $ready_to_bill_at When fixed-price work became billable. Its value counts from then.
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 * @property-read Project|null $project
 * @property-read RecurringTask|null $recurringTask
 */
#[Fillable([
    'client_id', 'project_id', 'title', 'description', 'status', 'priority', 'pricing_type',
    'hourly_rate', 'fixed_price', 'estimated_seconds', 'due_on', 'position',
])]
#[UseEloquentBuilder(TaskBuilder::class)]
class Task extends Model
{
    use Archivable;
    use HasContextLabel;

    /** A task key like ACME-12, in any case. */
    /** "ACME-12" in any case: a client code and a task number. */
    private const string KEY_PATTERN = '/^('.Client::CODE.')-(\d{1,9})$/i';

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => TaskStatus::Todo->value,
        'priority' => TaskPriority::Normal->value,
        'pricing_type' => PricingType::Hourly->value,
        'position' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            $task->number = self::nextNumber($task->client_id);
        });

        static::saving(function (Task $task): void {
            if ($task->isDirty('status')) {
                $task->completed_at = $task->status === TaskStatus::Done ? ($task->completed_at ?? now()) : null;
            }
        });

        static::updating(function (Task $task): void {
            $task->guardHistory();

            // A task moved to another client gets that client's next number, so its old key stops existing.
            if ($task->isDirty('client_id')) {
                $task->number = self::nextNumber($task->client_id);
            }
        });

        // Unbilled entries must match the task's pricing type, or the work would be counted twice.
        static::updated(function (Task $task): void {
            if ($task->wasChanged('pricing_type')) {
                $task->recalculateTimeEntries();
            }
        });
    }

    private static function nextNumber(int $clientId): int
    {
        $row = DB::selectOne('UPDATE clients SET last_task_number = last_task_number + 1 WHERE id = ? RETURNING last_task_number', [$clientId]);

        return (int) $row->last_task_number;
    }

    /** "Title — Client · Project", for task pickers. */
    public function optionLabel(): string
    {
        return $this->title.' — '.$this->contextLabel();
    }

    public function key(): string
    {
        $this->loadMissing('client');

        return $this->client->code.'-'.$this->number;
    }

    /**
     * @return array{code: string, number: int}|null null when $key is not a task key
     */
    public static function parseKey(string $key): ?array
    {
        if (preg_match(self::KEY_PATTERN, trim($key), $match) !== 1) {
            return null;
        }

        return ['code' => strtoupper($match[1]), 'number' => (int) $match[2]];
    }

    public function resolveHourlyRate(): int
    {
        $this->loadMissing(['client', 'project']);

        return $this->hourly_rate ?? $this->project?->resolveHourlyRate() ?? $this->client->hourly_rate;
    }

    /**
     * @return array{pricing_type: string, hourly_rate: int|null, currency: string}
     */
    public function timeEntrySnapshot(): array
    {
        $this->loadMissing('client');

        return [
            'pricing_type' => $this->pricing_type->value,
            'hourly_rate' => $this->pricing_type === PricingType::Hourly ? $this->resolveHourlyRate() : null,
            'currency' => $this->client->currency->value,
        ];
    }

    /** Re-snapshots finished entries that are not on any statement. */
    public function recalculateTimeEntries(): int
    {
        return $this->timeEntries()->finished()->unallocated()->update($this->timeEntrySnapshot());
    }

    /**
     * @param  CarbonImmutable|null  $at  Default now. Recurring tasks pass the period boundary.
     */
    public function markReadyToBill(?CarbonImmutable $at = null): void
    {
        if ($this->pricing_type !== PricingType::Fixed) {
            throw DomainRuleViolation::because('Only fixed-price tasks are marked as ready to bill; hourly work is billable once tracked.');
        }

        if ($this->ready_to_bill_at === null) {
            $this->forceFill(['ready_to_bill_at' => $at ?? now()])->save();
        }
    }

    public function unmarkReadyToBill(): void
    {
        $this->forceFill(['ready_to_bill_at' => null])->save();
    }

    public function isReadyToBill(): bool
    {
        return $this->ready_to_bill_at !== null;
    }

    public function effectiveHourlyRate(int $trackedSeconds): ?int
    {
        if ($this->pricing_type !== PricingType::Fixed || $this->fixed_price === null || $trackedSeconds <= 0) {
            return null;
        }

        return Money::roundHalfUpDiv($this->fixed_price * 3600, $trackedSeconds);
    }

    /** Uses `tracked_seconds` from TaskBuilder::withTracking() when loaded, like value(). */
    public function trackedSeconds(): int
    {
        if (! $this->hasAttribute('tracked_seconds')) {
            $this->loadSum('timeEntries as tracked_seconds', 'duration_seconds');
        }

        return (int) $this->getAttribute('tracked_seconds');
    }

    public function value(): int
    {
        if ($this->pricing_type === PricingType::Hourly && ! $this->hasAttribute('hourly_value')) {
            $this->loadSum('timeEntries as hourly_value', 'amount');
        }

        return match ($this->pricing_type) {
            PricingType::Hourly => (int) $this->getAttribute('hourly_value'),
            PricingType::Fixed => $this->fixed_price ?? 0,
            PricingType::NonBillable => 0,
        };
    }

    public function isFixedPriceBilled(): bool
    {
        return $this->statementItems()->fixed()->onActiveStatement()->exists();
    }

    /** The pricing type can no longer change: the task is ready to bill or billed. */
    public function isPricingLocked(): bool
    {
        return $this->getOriginal('ready_to_bill_at') !== null || $this->statementItems()->onActiveStatement()->exists() || $this->timeEntries()->allocated()->exists();
    }

    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->due_on?->lt(Period::localToday()) === true;
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

    /** @return BelongsTo<RecurringTask, $this> */
    public function recurringTask(): BelongsTo
    {
        return $this->belongsTo(RecurringTask::class);
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /** @return HasMany<Link, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    /** @return HasMany<ChecklistItem, $this> */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<StatementItem, $this> */
    public function statementItems(): HasMany
    {
        return $this->hasMany(StatementItem::class);
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'pricing_type' => PricingType::class,
            'hourly_rate' => 'integer',
            'fixed_price' => 'integer',
            'estimated_seconds' => 'integer',
            'due_on' => 'immutable_date',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'position' => 'integer',
            'completed_at' => 'immutable_datetime',
            'ready_to_bill_at' => 'immutable_datetime',
        ];
    }

    private function guardHistory(): void
    {
        if ($this->isDirty(['client_id', 'project_id']) && $this->timeEntries()->exists()) {
            throw DomainRuleViolation::because('A task with time entries cannot be moved to another client or project.');
        }

        if ($this->isDirty('pricing_type') && $this->isPricingLocked()) {
            throw DomainRuleViolation::because('The pricing type cannot be changed once the task is ready to bill or billed.');
        }

        if ($this->isDirty('fixed_price') && $this->getOriginal('ready_to_bill_at') !== null) {
            throw DomainRuleViolation::because('The fixed price is locked while the task is ready to bill.');
        }

        if ($this->isDirty('ready_to_bill_at') && $this->ready_to_bill_at === null && $this->statementItems()->onActiveStatement()->exists()) {
            throw DomainRuleViolation::because('The task is already on a statement.');
        }
    }
}
