<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Amounts of several currencies as one approximate CZK figure, for overviews only.
 * Statements, rates and stored amounts always stay in their own currency.
 */
final readonly class CzkTotal
{
    public function __construct(
        public int $amount,
        /** Oldest ČNB fixing used. */
        public CarbonImmutable $rateOn,
    ) {}

    /** Oldest of the newest rates, for the page footer. Null before the first ČNB fetch. */
    public static function rateDate(): ?CarbonImmutable
    {
        return self::rates()->min('rate_on');
    }

    /**
     * Converts with the newest rate as of today. Null when a currency has no rate yet.
     *
     * @param  array<string, int>  $amounts  minor units keyed by currency code
     */
    public static function of(array $amounts): ?self
    {
        $rates = self::rates();
        $total = 0;
        $rateOn = Period::localToday();

        foreach ($amounts as $code => $amount) {
            if ($code === Currency::CZK->value) {
                $total += $amount;

                continue;
            }

            $rate = $rates->get($code);

            if ($rate === null) {
                return null;
            }

            $total += $rate->toCzk($amount);
            $rateOn = $rate->rate_on->lt($rateOn) ? $rate->rate_on : $rateOn;
        }

        return new self($total, $rateOn);
    }

    /**
     * Newest rate of each currency as of today, read once per request.
     *
     * @return Collection<string, ExchangeRate>
     */
    private static function rates(): Collection
    {
        return once(fn (): Collection => ExchangeRate::query()
            ->latestOn(Period::localToday())
            ->get()
            ->keyBy(fn (ExchangeRate $rate): string => $rate->currency->value));
    }
}
