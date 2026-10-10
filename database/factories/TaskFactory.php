<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PricingType;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => fake()->sentence(4),
            'pricing_type' => PricingType::Hourly,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(['client_id' => $project->client_id, 'project_id' => $project->id]);
    }

    public function fixed(int $price): static
    {
        return $this->state(['pricing_type' => PricingType::Fixed, 'fixed_price' => $price]);
    }

    public function nonBillable(): static
    {
        return $this->state(['pricing_type' => PricingType::NonBillable]);
    }
}
