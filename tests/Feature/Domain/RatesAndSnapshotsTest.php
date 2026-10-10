<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Currency;
use App\Enums\PricingType;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RatesAndSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_rate_overrides_project_rate_which_overrides_client_rate(): void
    {
        $client = Client::factory()->create(['hourly_rate' => 120_000]);
        $project = Project::factory()->create(['client_id' => $client, 'hourly_rate' => 140_000]);

        $this->assertSame(120_000, Task::factory()->create(['client_id' => $client, 'project_id' => null])->resolveHourlyRate());
        $this->assertSame(140_000, Task::factory()->forProject($project)->create()->resolveHourlyRate());
        $this->assertSame(160_000, Task::factory()->forProject($project)->create(['hourly_rate' => 160_000])->resolveHourlyRate());
    }

    public function test_freezes_rate_currency_and_pricing_type_when_the_entry_is_finished(): void
    {
        $client = Client::factory()->eur(6_000)->create();
        $entry = TimeEntry::factory()->lasting(5400)->create(['task_id' => Task::factory()->create(['client_id' => $client])]);

        $this->assertSame(PricingType::Hourly, $entry->pricing_type);
        $this->assertSame(6_000, $entry->hourly_rate);
        $this->assertSame(Currency::EUR, $entry->currency);
        $this->assertSame(5400, $entry->duration_seconds);
        $this->assertSame(9_000, $entry->amount);
    }

    public function test_keeps_historical_amounts_when_rates_change_later(): void
    {
        $project = Project::factory()->create(['hourly_rate' => null]);
        $task = Task::factory()->forProject($project)->create();
        $entry = TimeEntry::factory()->lasting(3600)->create(['task_id' => $task]);

        $project->client->update(['hourly_rate' => 150_000]);
        $project->update(['hourly_rate' => 170_000]);
        $task->update(['hourly_rate' => 190_000]);

        $this->assertSame(120_000, $entry->fresh()?->amount);
        $this->assertSame(190_000, TimeEntry::factory()->lasting(3600)->create(['task_id' => $task])->amount);
    }

    public function test_correcting_times_keeps_the_snapshot_until_explicit_recalculation(): void
    {
        $task = Task::factory()->create();
        $entry = TimeEntry::factory()->lasting(3600)->create(['task_id' => $task]);
        $task->update(['hourly_rate' => 200_000]);

        $entry->update(['ended_at' => $entry->started_at->addMinutes(30)]);

        $this->assertSame(1800, $entry->duration_seconds);
        $this->assertSame(120_000, $entry->hourly_rate);
        $this->assertSame(60_000, $entry->amount);

        $this->assertSame(1, $task->recalculateTimeEntries());
        $this->assertSame(100_000, $entry->fresh()?->amount);
    }

    public function test_takes_a_new_snapshot_when_the_entry_is_moved_to_another_task(): void
    {
        $client = Client::factory()->create();
        $hourly = Task::factory()->create(['client_id' => $client]);
        $fixed = Task::factory()->fixed(500_000)->create(['client_id' => $client]);
        $entry = TimeEntry::factory()->lasting(3600)->create(['task_id' => $hourly]);

        $entry->update(['task_id' => $fixed->id]);

        $this->assertSame(PricingType::Fixed, $entry->pricing_type);
        $this->assertNull($entry->hourly_rate);
        $this->assertSame(0, $entry->amount);
    }

    #[DataProvider('roundingCases')]
    public function test_database_amount_rounds_like_php(int $seconds, int $rate): void
    {
        $task = Task::factory()->create(['hourly_rate' => $rate]);
        $entry = TimeEntry::factory()->lasting($seconds)->create(['task_id' => $task]);

        $this->assertSame(Money::roundHalfUpDiv($seconds * $rate, 3600), $entry->amount);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function roundingCases(): array
    {
        return [
            'below half' => [17, 100],
            'exactly half' => [18, 100],
            'odd rate' => [7, 99_999],
            'repeating fraction up' => [2400, 100_000],
            'almost a day' => [86_399, 123_457],
        ];
    }

    public function test_truncates_sub_second_precision_so_the_duration_is_exact(): void
    {
        $start = now()->subHour()->setMicrosecond(999_999);
        $entry = TimeEntry::factory()->create(['started_at' => $start, 'ended_at' => $start->addSeconds(10)->setMicrosecond(1)]);

        $this->assertSame(10, $entry->duration_seconds);
    }
}
