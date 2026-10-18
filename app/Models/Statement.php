<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\StatementStatus;
use App\Exceptions\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Statement of work for a client, the attachment to an invoice. Status changes go through App\Actions\Billing.
 *
 * @property int $id
 * @property int $client_id
 * @property Currency $currency
 * @property StatementStatus $status
 * @property CarbonImmutable|null $sent_on
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 */
#[Fillable(['note'])]
class Statement extends Model
{
    protected $attributes = [
        'status' => StatementStatus::Draft->value,
    ];

    protected static function booted(): void
    {
        // A sent statement is final; CancelStatement only changes its status.
        static::updating(function (Statement $statement): void {
            if ($statement->getOriginal('status') !== StatementStatus::Draft && array_keys($statement->getDirty()) !== ['status']) {
                throw DomainRuleViolation::because('Only draft statements can be changed.');
            }
        });

        static::deleting(function (Statement $statement): void {
            if (! $statement->isDraft()) {
                throw DomainRuleViolation::because('Only draft statements can be deleted; cancel sent ones instead.');
            }
        });
    }

    /** Reloads with a row lock and checks the status. Call inside a transaction. */
    public function lockInStatus(StatementStatus $expected): self
    {
        $locked = self::query()->lockForUpdate()->findOrFail($this->id);

        if ($locked->status !== $expected) {
            throw DomainRuleViolation::because('This operation requires a statement in status ":status".', [
                'status' => $expected->getLabel(),
            ]);
        }

        return $locked;
    }

    public function nextItemPosition(): int
    {
        return (int) StatementItem::query()->where('statement_id', $this->id)->max('position') + 1;
    }

    /** Sum of the items: from loaded items, or a query. */
    public function total(): int
    {
        return (int) ($this->relationLoaded('items') ? $this->items->sum('amount') : $this->items()->sum('amount'));
    }

    public function isDraft(): bool
    {
        return $this->status === StatementStatus::Draft;
    }

    public function isSent(): bool
    {
        return $this->status === StatementStatus::Sent;
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<StatementItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StatementItem::class)->orderBy('position')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'status' => StatementStatus::class,
            'sent_on' => 'immutable_date',
        ];
    }
}
