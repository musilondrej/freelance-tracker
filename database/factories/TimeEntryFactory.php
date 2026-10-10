<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Finished one-hour entry by default. The snapshot is taken by the model, never by the factory.
 *
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::now()->subDay()->setTime(9, 0);

        return [
            'task_id' => Task::factory(),
            'started_at' => $start,
            'ended_at' => $start->addHour(),
        ];
    }

    public function lasting(int $seconds, ?CarbonImmutable $startedAt = null): static
    {
        return $this->state(function (array $attributes) use ($seconds, $startedAt): array {
            /** @var CarbonImmutable $start */
            $start = $startedAt ?? $attributes['started_at'];

            return ['started_at' => $start, 'ended_at' => $start->addSeconds($seconds)];
        });
    }

    public function running(): static
    {
        return $this->state(['started_at' => CarbonImmutable::now()->subMinutes(5), 'ended_at' => null]);
    }
}
