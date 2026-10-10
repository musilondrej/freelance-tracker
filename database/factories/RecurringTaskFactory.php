<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingTiming;
use App\Enums\PricingType;
use App\Enums\RecurrenceFrequency;
use App\Models\Client;
use App\Models\RecurringTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Monthly fixed fee of 2 000 Kč billed after the period, starting this month.
 *
 * @extends Factory<RecurringTask>
 */
class RecurringTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => 'Správa webu',
            'pricing_type' => PricingType::Fixed,
            'fixed_price' => 200_000,
            'frequency' => RecurrenceFrequency::Monthly,
            'billing_timing' => BillingTiming::InArrears,
            'starts_on' => now()->startOfMonth()->toDateString(),
        ];
    }
}
