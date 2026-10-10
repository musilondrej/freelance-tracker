<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Actions\Billing\AddTimeEntriesToStatement;
use App\Actions\Billing\CreateStatement;
use App\Actions\Billing\SendStatement;
use App\Enums\BudgetStatus;
use App\Enums\Currency;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Reporting\CurrencyTotals;
use App\Reporting\DailyTrackedTime;
use App\Reporting\FinancialSummary;
use App\Reporting\NamedTotals;
use App\Reporting\ProjectStats;
use App\Reporting\UnbilledWork;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    }

    public function test_separates_work_value_to_invoice_in_drafts_and_billed(): void
    {
        [$client, $a] = $this->briefScenario();

        $this->assertSame(1_000_000, $this->czk()->workValue);
        $this->assertSame(1_000_000, $this->czk()->unbilled);
        $this->assertSame(0, $this->czk()->billed);

        $statement = app(CreateStatement::class)->handle($client, [$a]);
        $this->assertSame(400_000, $this->czk()->unbilled);
        $this->assertSame(600_000, $this->czk()->inDrafts);
        $this->assertSame(0, $this->czk()->billed);

        app(SendStatement::class)->handle($statement, now()->toImmutable());
        $this->assertSame(600_000, $this->czk()->billed);
        $this->assertSame(400_000, $this->czk()->unbilled);
        $this->assertSame(0, $this->czk()->inDrafts);

        // Billed history survives a later rate change.
        $client->update(['hourly_rate' => 300_000]);
        $a->recalculateTimeEntries();
        $this->assertSame(600_000, $this->czk()->billed);
        $this->assertSame(1_000_000, $this->czk()->workValue);
    }

    public function test_reports_billable_and_non_billable_time_and_hourly_rates(): void
    {
        $this->briefScenario();

        $this->assertSame(12 * 3600, $this->czk()->billableSeconds);
        $this->assertSame(3 * 3600, $this->czk()->nonBillableSeconds);
        $this->assertSame(83_333, $this->czk()->averageHourlyRate()); // 10 000 / 12 h
        $this->assertSame(66_667, $this->czk()->realHourlyRate()); // 10 000 / 15 h, non-billable time included
        $this->assertNull(new CurrencyTotals(Currency::CZK, fixedValue: 500_000)->realHourlyRate());
    }

    public function test_keeps_currencies_apart(): void
    {
        TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->create()]);
        $eur = Client::factory()->eur(6_000)->create();
        TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->create(['client_id' => $eur])]);

        $totals = FinancialSummary::for(Period::thisMonth());

        $this->assertSame(['CZK', 'EUR'], array_keys($totals));
        $this->assertSame(120_000, $totals['CZK']->workValue);
        $this->assertSame(6_000, $totals['EUR']->workValue);
    }

    public function test_counts_fixed_work_in_the_month_it_became_ready_to_bill(): void
    {
        $task = Task::factory()->fixed(2_000_000)->create();
        TimeEntry::factory()->lasting(3600, now()->subMonth()->toImmutable())->create(['task_id' => $task]);

        $this->assertSame([], FinancialSummary::for(Period::thisMonth()));

        $task->markReadyToBill();

        $this->assertSame(2_000_000, $this->czk()->workValue);
        $this->assertSame(0, FinancialSummary::for(Period::between('2026-09-01', '2026-09-30'))['CZK']->workValue);
    }

    public function test_attributes_entries_to_periods_by_their_start_in_prague_time(): void
    {
        // 2026-09-30 23:30 in Prague is 21:30 UTC. It must count in September, not October.
        $start = now()->setTimezone('Europe/Prague')->setDate(2026, 9, 30)->setTime(23, 30)->utc()->toImmutable();
        TimeEntry::factory()->lasting(3600, $start)->create();

        $this->assertSame(120_000, FinancialSummary::for(Period::between('2026-09-01', '2026-09-30'))['CZK']->workValue);
        $this->assertSame(0, $this->czk()->workValue);
    }

    public function test_filters_and_breaks_totals_down_by_client_and_project(): void
    {
        [$client] = $this->briefScenario();
        $project = Project::factory()->create(['client_id' => $client->id]);
        TimeEntry::factory()->lasting(1800)->create(['task_id' => Task::factory()->forProject($project)->create()]);
        $eur = Client::factory()->eur()->create();
        TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->create(['client_id' => $eur])]);

        $this->assertSame(60_000, FinancialSummary::for(Period::thisMonth(), projectId: $project->id)['CZK']->workValue);
        $this->assertSame(1_060_000, FinancialSummary::for(Period::thisMonth(), clientId: $client->id)['CZK']->workValue);

        $workValues = fn (array $lines): array => array_map(
            fn (NamedTotals $line): array => [$line->name, $line->totals->currency->value, $line->totals->workValue],
            $lines,
        );

        $this->assertSame(
            [[$client->name, 'CZK', 1_060_000], [$eur->name, 'EUR', 6_000]],
            $workValues(FinancialSummary::breakdown('client_id', Period::thisMonth())),
        );
        $this->assertSame(
            [[__('No project'), 'CZK', 1_000_000], [$project->name, 'CZK', 60_000], [__('No project'), 'EUR', 6_000]],
            $workValues(FinancialSummary::breakdown('project_id', Period::thisMonth())),
        );
        $this->assertSame(1_060_000, $this->czk()->workValue);
    }

    public function test_lists_unbilled_work_per_task_consistently_with_the_summary(): void
    {
        [$client, $a, $b] = $this->briefScenario();
        $statement = app(CreateStatement::class)->handle($client);
        app(AddTimeEntriesToStatement::class)->handle($statement, [$a->timeEntries()->value('id')]);

        $tasks = UnbilledWork::tasks()->get();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $tasks->pluck('id')->all());
        $this->assertSame(880_000, $this->czk()->unbilled);
        $this->assertSame(880_000, $tasks->sum(fn (Task $task) => UnbilledWork::amount($task)));
    }

    public function test_project_budget_is_consumed_by_work_value_not_fixed_price_times_hours(): void
    {
        $project = Project::factory()->create(['budget' => 10_000_000, 'estimated_seconds' => 50 * 3600]);
        $hourly = Task::factory()->forProject($project)->create();
        TimeEntry::factory()->count(10)->lasting(3600)->create(['task_id' => $hourly]);
        $fixed = Task::factory()->forProject($project)->fixed(5_300_000)->create();
        TimeEntry::factory()->count(20)->lasting(3600)->create(['task_id' => $fixed]);
        $fixed->markReadyToBill();
        $internal = Task::factory()->forProject($project)->nonBillable()->create();
        TimeEntry::factory()->count(5)->lasting(3600)->create(['task_id' => $internal]);

        $stats = ProjectStats::for($project);

        $this->assertSame(6_500_000, $stats->project->budgetConsumed());
        $this->assertSame(6_500_000, $stats->totals->workValue);
        $this->assertSame(3_500_000, $stats->project->budgetRemaining());
        $this->assertSame(65, $stats->project->budgetUsagePercent());
        $this->assertSame(35 * 3600, $stats->trackedSeconds);
        $this->assertSame(50 * 3600, $stats->estimatedSeconds);
        $this->assertSame(BudgetStatus::Ok, $stats->project->budgetStatus());
    }

    public function test_planned_fixed_work_counts_towards_the_budget(): void
    {
        $project = Project::factory()->create(['budget' => 1_000_000]);
        TimeEntry::factory()->count(7)->lasting(3600)->create(['task_id' => Task::factory()->forProject($project)->create()]);
        Task::factory()->forProject($project)->fixed(500_000)->create();

        $stats = ProjectStats::for($project);

        $this->assertSame(840_000, $stats->project->budgetConsumed());
        $this->assertSame(1_340_000, $stats->project->budgetPlanned());
        $this->assertSame(BudgetStatus::Over, $stats->project->budgetStatus());
    }

    public function test_daily_tracked_time_uses_local_days_and_sums_to_the_monthly_time(): void
    {
        $this->briefScenario();
        // 22:30 UTC on the 14th is 00:30 on the 15th in Prague.
        $eurTask = Task::factory()->create(['client_id' => Client::factory()->eur()->create()]);
        TimeEntry::factory()->lasting(3600, CarbonImmutable::parse('2026-10-14 22:30', 'UTC'))->create(['task_id' => $eurTask]);

        $days = DailyTrackedTime::for(Period::thisMonth());

        $this->assertSame(['CZK', 'EUR'], array_keys($days));
        $this->assertSame(['2026-10-15' => 3600], $days['EUR']);
        $this->assertSame(
            (int) TimeEntry::query()->startedIn(Period::thisMonth())->sum('duration_seconds'),
            array_sum(array_map(array_sum(...), $days)),
        );
    }

    public function test_orders_projects_by_the_share_of_the_budget_consumed(): void
    {
        [$low, $high, $noBudget] = collect([1_000_000, 200_000, null])->map(function (?int $budget): Project {
            $project = Project::factory()->create(['budget' => $budget]);
            TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->forProject($project)->create()]);

            return $project;
        })->all();

        $this->assertSame(
            [$high->id, $low->id, $noBudget->id],
            Project::query()->withBudgetUsage()->orderByBudgetUsage()->pluck('id')->all(),
        );
    }

    #[DataProvider('budgetStatuses')]
    public function test_derives_the_budget_status_from_the_displayed_percent(int $consumed, int $planned, ?int $budget, ?BudgetStatus $status): void
    {
        $this->assertSame($status, ProjectStats::status($consumed, $planned, $budget));
    }

    /** @return array<string, array{int, int, ?int, ?BudgetStatus}> */
    public static function budgetStatuses(): array
    {
        return [
            'no budget' => [5_000, 5_000, null, null],
            'rounds to 80 % → near' => [7_960, 7_960, 10_000, BudgetStatus::Near],
            'just below' => [7_949, 7_949, 10_000, BudgetStatus::Ok],
            'planned over budget' => [5_000, 10_001, 10_000, BudgetStatus::Over],
            'consumed over budget' => [10_001, 10_001, 10_000, BudgetStatus::Over],
            'zero budget, nothing used' => [0, 0, 0, BudgetStatus::Ok],
            'zero budget, used' => [1, 1, 0, BudgetStatus::Over],
        ];
    }

    /**
     * Task A is hourly, 5 h at 1 200. Task B is fixed at 4 000. Task C is non-billable, 3 h.
     *
     * @return array{Client, Task, Task, Task}
     */
    private function briefScenario(): array
    {
        $client = Client::factory()->create(['hourly_rate' => 120_000]);
        $a = Task::factory()->create(['client_id' => $client]);
        TimeEntry::factory()->count(5)->lasting(3600, now()->subDays(2)->toImmutable())->create(['task_id' => $a]);
        $b = Task::factory()->fixed(400_000)->create(['client_id' => $client]);
        TimeEntry::factory()->lasting(7 * 3600, now()->subDays(3)->toImmutable())->create(['task_id' => $b]);
        $b->markReadyToBill();
        $c = Task::factory()->nonBillable()->create(['client_id' => $client]);
        TimeEntry::factory()->lasting(3 * 3600, now()->subDays(1)->toImmutable())->create(['task_id' => $c]);

        return [$client, $a, $b, $c];
    }

    private function czk(): CurrencyTotals
    {
        return FinancialSummary::for(Period::thisMonth())['CZK'];
    }
}
