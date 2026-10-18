<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\Currency;
use App\Support\Money;

/**
 * Financial figures of one currency. Never add two CurrencyTotals of different currencies together.
 * Amounts are minor units, times are seconds. See docs/billing.md for the definitions.
 */
final readonly class CurrencyTotals
{
    /** Hourly entry amounts plus fixed prices that became ready to bill. */
    public int $workValue;

    public function __construct(
        public Currency $currency,
        public int $hourlyValue = 0,
        public int $fixedValue = 0,
        public int $unbilled = 0,
        public int $inDrafts = 0,
        public int $billed = 0,
        public int $billableSeconds = 0,
        public int $nonBillableSeconds = 0,
    ) {
        $this->workValue = $hourlyValue + $fixedValue;
    }

    public function format(int $amount): string
    {
        return Money::format($amount, $this->currency);
    }

    /** Work value per billable hour, rounded half up. Null without billable time. */
    public function averageHourlyRate(): ?int
    {
        return Money::perHour($this->workValue, $this->billableSeconds);
    }

    /**
     * Work value per tracked hour, non-billable time included. Null without time.
     * A fixed price counts on one day, so short periods can be off.
     */
    public function realHourlyRate(): ?int
    {
        return Money::perHour($this->workValue, $this->trackedSeconds());
    }

    public function trackedSeconds(): int
    {
        return $this->billableSeconds + $this->nonBillableSeconds;
    }

    public function isEmpty(): bool
    {
        return $this->workValue === 0 && $this->unbilled === 0 && $this->inDrafts === 0 && $this->billed === 0
            && $this->billableSeconds === 0 && $this->nonBillableSeconds === 0;
    }
}
