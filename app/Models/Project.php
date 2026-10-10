<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BudgetStatus;
use App\Enums\ProjectStatus;
use App\Models\Builders\ProjectBuilder;
use App\Models\Concerns\Archivable;
use App\Reporting\ProjectStats;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property int|null $hourly_rate Overrides the client's rate. Minor units per hour.
 * @property int|null $budget In minor units of the client's currency.
 * @property int|null $estimated_seconds
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $due_on
 * @property string|null $notes
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 */
#[Fillable([
    'client_id', 'name', 'description', 'status', 'hourly_rate', 'budget', 'estimated_seconds', 'starts_on', 'due_on', 'notes',
])]
#[UseEloquentBuilder(ProjectBuilder::class)]
class Project extends Model
{
    use Archivable;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ProjectStatus::Active->value,
    ];

    public function resolveHourlyRate(): int
    {
        return $this->hourly_rate ?? $this->client->hourly_rate;
    }

    /** The budget methods need ProjectBuilder::withBudgetUsage(). */
    public function budgetConsumed(): int
    {
        return (int) $this->getAttribute('consumed');
    }

    /** Consumed plus fixed prices that are not ready to bill yet. */
    public function budgetPlanned(): int
    {
        return $this->budgetConsumed() + (int) $this->getAttribute('pending');
    }

    public function budgetRemaining(): ?int
    {
        return $this->budget === null ? null : $this->budget - $this->budgetConsumed();
    }

    public function budgetUsagePercent(): ?int
    {
        return ProjectStats::usagePercent($this->budgetConsumed(), $this->budget);
    }

    public function budgetStatus(): ?BudgetStatus
    {
        return ProjectStats::status($this->budgetConsumed(), $this->budgetPlanned(), $this->budget);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasManyThrough<TimeEntry, Task, $this> */
    public function timeEntries(): HasManyThrough
    {
        return $this->hasManyThrough(TimeEntry::class, Task::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'hourly_rate' => 'integer',
            'budget' => 'integer',
            'estimated_seconds' => 'integer',
            'starts_on' => 'immutable_date',
            'due_on' => 'immutable_date',
        ];
    }
}
