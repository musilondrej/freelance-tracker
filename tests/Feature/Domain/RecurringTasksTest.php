<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Actions\Recurring\GenerateRecurringTasks;
use App\Enums\BillingTiming;
use App\Enums\PricingType;
use App\Enums\RecurrenceFrequency;
use App\Exceptions\DomainRuleViolation;
use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Reporting\FinancialSummary;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RecurringTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Europe/Prague'));
    }

    private function generate(): int
    {
        return app(GenerateRecurringTasks::class)->handle();
    }

    public function test_creates_the_current_period_task_once(): void
    {
        $recurring = RecurringTask::factory()->create(['starts_on' => '2026-10-01']);

        $this->assertSame(1, $this->generate());
        $this->assertSame(0, $this->generate());
        $this->assertSame(0, Artisan::call('recurring:generate'));

        $task = Task::query()->sole();
        $this->assertSame('Správa webu – říjen 2026', $task->title);
        $this->assertSame($recurring->id, $task->recurring_task_id);
        $this->assertSame('2026-10-01', $task->period_start?->toDateString());
        $this->assertSame('2026-10-31', $task->period_end?->toDateString());
        $this->assertSame('2026-10-31', $task->due_on?->toDateString());
        $this->assertSame(PricingType::Fixed, $task->pricing_type);
        $this->assertSame(200_000, $task->fixed_price);
        $this->assertSame($recurring->client_id, $task->client_id);
    }

    public function test_catches_up_missed_periods_and_stops_at_the_end_date(): void
    {
        RecurringTask::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2026-09-30']);

        $this->assertSame(3, $this->generate());
        $this->assertSame(
            ['Správa webu – červenec 2026', 'Správa webu – srpen 2026', 'Správa webu – září 2026'],
            Task::query()->orderBy('period_start')->pluck('title')->all(),
        );
    }

    public function test_does_not_create_future_periods(): void
    {
        RecurringTask::factory()->create(['starts_on' => '2026-11-01']);

        $this->assertSame(0, $this->generate());

        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:30', 'Europe/Prague'));
        $this->assertSame(1, $this->generate());
    }

    public function test_month_end_schedules_do_not_drift(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-15', 'Europe/Prague'));
        RecurringTask::factory()->create(['starts_on' => '2026-01-31']);

        $this->generate();

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31'],
            Task::query()->orderBy('period_start')->get()->map(fn (Task $t) => $t->period_start?->toDateString())->all(),
        );
    }

    #[DataProvider('schedules')]
    public function test_period_labels_and_ends_per_schedule(RecurrenceFrequency $frequency, int $interval, string $start, string $title, string $end): void
    {
        RecurringTask::factory()->create(['frequency' => $frequency, 'interval' => $interval, 'starts_on' => $start, 'title' => 'Hosting']);

        $this->generate();

        $task = Task::query()->orderByDesc('period_start')->firstOrFail();
        $this->assertSame($title, $task->title);
        $this->assertSame($end, $task->period_end?->toDateString());
    }

    /** @return array<string, array{RecurrenceFrequency, int, string, string, string}> */
    public static function schedules(): array
    {
        return [
            'quarterly' => [RecurrenceFrequency::Quarterly, 1, '2026-10-01', 'Hosting – 4. čtvrtletí 2026', '2026-12-31'],
            'yearly' => [RecurrenceFrequency::Yearly, 1, '2026-01-01', 'Hosting – 2026', '2026-12-31'],
            'yearly from march' => [RecurrenceFrequency::Yearly, 1, '2026-03-01', 'Hosting – 1. 3. 2026 – 28. 2. 2027', '2027-02-28'],
            'every 2 months' => [RecurrenceFrequency::Monthly, 2, '2026-09-01', 'Hosting – 1. 9. 2026 – 31. 10. 2026', '2026-10-31'],
        ];
    }

    public function test_fee_billed_in_advance_is_ready_at_period_start_and_attributed_to_its_period(): void
    {
        RecurringTask::factory()->create(['starts_on' => '2026-08-01', 'billing_timing' => BillingTiming::InAdvance]);

        $this->generate();

        $october = Task::query()->where('period_start', '2026-10-01')->sole();
        $this->assertSame(
            CarbonImmutable::parse('2026-10-01', 'Europe/Prague')->utc()->toIso8601String(),
            $october->ready_to_bill_at?->toIso8601String(),
        );
        $this->assertSame(200_000, FinancialSummary::for(Period::between('2026-08-01', '2026-08-31'))['CZK']->workValue);
        $this->assertSame(200_000, FinancialSummary::for(Period::thisMonth())['CZK']->workValue);
    }

    public function test_fee_billed_in_arrears_is_ready_after_the_period(): void
    {
        RecurringTask::factory()->create(['starts_on' => '2026-10-01', 'billing_timing' => BillingTiming::InArrears]);

        $this->generate();
        $this->assertNull(Task::query()->sole()->ready_to_bill_at);

        $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00', 'Europe/Prague'));
        $this->generate();

        $october = Task::query()->where('period_start', '2026-10-01')->sole();
        $this->assertSame(
            CarbonImmutable::parse('2026-11-01', 'Europe/Prague')->utc()->toIso8601String(),
            $october->ready_to_bill_at?->toIso8601String(),
        );
        $this->assertSame(2, Task::query()->count());
    }

    public function test_hourly_template_generates_tasks_billing_tracked_time(): void
    {
        $recurring = RecurringTask::factory()->create([
            'starts_on' => '2026-10-01', 'pricing_type' => PricingType::Hourly, 'fixed_price' => null, 'hourly_rate' => 150_000,
        ]);

        $this->generate();
        $task = $recurring->tasks()->sole();
        TimeEntry::factory()->lasting(3600)->create(['task_id' => $task]);

        $this->assertSame(150_000, (int) $task->timeEntries()->sum('amount'));
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $change
     */
    #[DataProvider('manualPricingChanges')]
    public function test_task_with_pricing_changed_by_hand_is_not_marked_ready(array $template, array $change): void
    {
        RecurringTask::factory()->create(['starts_on' => '2026-10-01', ...$template]);
        $this->generate();
        Task::query()->sole()->update($change);

        $this->travelTo(CarbonImmutable::parse('2026-11-02', 'Europe/Prague'));
        $this->generate();

        $this->assertNull(Task::query()->where('period_start', '2026-10-01')->sole()->ready_to_bill_at);
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function manualPricingChanges(): array
    {
        return [
            'fixed template made non-billable' => [[], ['pricing_type' => PricingType::NonBillable]],
            'hourly template made fixed' => [
                ['pricing_type' => PricingType::Hourly, 'fixed_price' => null],
                ['pricing_type' => PricingType::Fixed, 'fixed_price' => 500_000],
            ],
        ];
    }

    public function test_archived_template_or_client_stops_generation(): void
    {
        RecurringTask::factory()->create(['starts_on' => '2026-10-01'])->archive();
        $client = Client::factory()->create();
        RecurringTask::factory()->create(['starts_on' => '2026-10-01', 'client_id' => $client]);
        $client->archive();

        $this->assertSame(0, $this->generate());
    }

    public function test_schedule_is_locked_once_tasks_were_generated(): void
    {
        $recurring = RecurringTask::factory()->create(['starts_on' => '2026-10-01']);
        $this->generate();

        $this->expectException(DomainRuleViolation::class);
        $recurring->refresh()->update(['frequency' => RecurrenceFrequency::Yearly]);
    }

    public function test_database_requires_the_project_to_belong_to_the_client(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);
        RecurringTask::factory()->create(['project_id' => $project->id]);
    }

    public function test_database_guards_against_duplicate_periods(): void
    {
        $recurring = RecurringTask::factory()->create(['starts_on' => '2026-10-01']);
        $this->generate();
        $task = Task::factory()->create(['client_id' => $recurring->client_id])
            ->forceFill(['recurring_task_id' => $recurring->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31']);

        $this->expectException(UniqueConstraintViolationException::class);
        $task->save();
    }
}
