<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of ExchangeRate
 *
 * @extends Builder<TModel>
 */
final class ExchangeRateBuilder extends Builder
{
    /** The newest rate of each currency fixed on or before the date. */
    public function latestOn(CarbonImmutable $date): static
    {
        return $this->where('exchange_rates.rate_on', '<=', $date->toDateString())
            ->selectRaw('DISTINCT ON (exchange_rates.currency) exchange_rates.*')
            ->orderBy('exchange_rates.currency')
            ->latest('exchange_rates.rate_on');
    }
}
