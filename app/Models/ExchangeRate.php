<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Models\Builders\ExchangeRateBuilder;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * ČNB rate of one currency on one day.
 *
 * @property int $id
 * @property CarbonImmutable $rate_on
 * @property Currency $currency
 * @property int $amount Units of the currency the rate is for, e.g. 100 for JPY.
 * @property int $rate CZK for `amount` units, in thousandths.
 */
#[Fillable(['rate_on', 'currency', 'amount', 'rate'])]
#[UseEloquentBuilder(ExchangeRateBuilder::class)]
class ExchangeRate extends Model
{
    /** Minor units of the currency to haléře, rounded half up. */
    public function toCzk(int $amount): int
    {
        return Money::roundHalfUpDivSigned($amount * $this->rate, $this->amount * 1000);
    }

    protected function casts(): array
    {
        return [
            'rate_on' => 'immutable_date',
            'currency' => Currency::class,
            'amount' => 'integer',
            'rate' => 'integer',
        ];
    }
}
