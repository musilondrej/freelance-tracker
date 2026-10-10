<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('??###')),
            'email' => fake()->unique()->safeEmail(),
            'hourly_rate' => 120_000, // 1 200,00 Kč/h
            'currency' => Currency::CZK,
        ];
    }

    public function eur(int $hourlyRate = 6_000): static
    {
        return $this->state(['currency' => Currency::EUR, 'hourly_rate' => $hourlyRate]);
    }
}
