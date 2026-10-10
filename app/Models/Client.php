<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Exceptions\DomainRuleViolation;
use App\Models\Builders\ArchivableBuilder;
use App\Models\Concerns\Archivable;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

/**
 * @property int $id
 * @property string $name
 * @property string $code Task key prefix, like ACME in ACME-12.
 * @property int $last_task_number Last used task number. Numbers of deleted tasks are not reused.
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $registration_number
 * @property string|null $vat_number
 * @property string|null $street
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $country_code
 * @property string|null $notes
 * @property int $hourly_rate Default rate in minor units per hour.
 * @property Currency $currency The only place with a currency. Everything else uses it.
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable([
    'name', 'code', 'company_name', 'email', 'phone', 'website', 'registration_number', 'vat_number',
    'street', 'city', 'postal_code', 'country_code', 'notes', 'hourly_rate', 'currency',
])]
#[UseEloquentBuilder(ArchivableBuilder::class)]
class Client extends Model
{
    /** Task key prefix: 2–10 letters or digits, starting with a letter. The DB check clients_code_format matches it. */
    public const string CODE = '[A-Z][A-Z0-9]{1,9}';

    public const string CODE_PATTERN = '/^'.self::CODE.'$/';

    use Archivable;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Client $client): void {
            if ($client->isDirty('currency') && $client->hasFinancialHistory()) {
                throw DomainRuleViolation::because('The currency cannot be changed once the client has time entries or statements.');
            }
        });
    }

    /** Suggests a task key prefix from the name, like ACME for "Acme s.r.o.". Adds a number while the code is taken. */
    public static function suggestCode(string $name): string
    {
        $base = Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->ltrim('0123456789')
            ->substr(0, 4)
            ->whenEmpty(fn (Stringable $code): Stringable => $code->append('C'))
            ->padRight(2, 'X')
            ->value();

        $code = $base;

        for ($suffix = 2; self::query()->where('code', $code)->exists(); $suffix++) {
            $code = $base.$suffix;
        }

        return $code;
    }

    public function address(): string
    {
        return collect([$this->street, trim($this->postal_code.' '.$this->city), $this->country_code])->filter()->implode(', ');
    }

    public function hasFinancialHistory(): bool
    {
        return $this->timeEntries()->exists() || $this->statements()->exists();
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
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

    /** @return HasMany<Statement, $this> */
    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class);
    }

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'integer',
            'last_task_number' => 'integer',
            'currency' => Currency::class,
        ];
    }
}
