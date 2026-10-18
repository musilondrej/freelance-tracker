<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Actions\Tasks\MoveTask;
use App\Enums\PricingType;
use App\Enums\TaskStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_hourly_work_is_valued_by_its_entries(): void
    {
        $task = Task::factory()->create();
        TimeEntry::factory()->count(5)->lasting(3600)->create(['task_id' => $task]);

        $this->assertSame(600_000, $task->value());
        $this->assertSame(5 * 3600, $task->trackedSeconds());
        $this->assertSame(600_000, Task::query()->withTracking()->sole()->value());
    }

    public function test_fixed_and_non_billable_time_carries_no_value(): void
    {
        $fixed = Task::factory()->fixed(500_000)->create();
        TimeEntry::factory()->count(7)->lasting(3600)->create(['task_id' => $fixed]);
        $entry = TimeEntry::factory()->lasting(3 * 3600)->create(['task_id' => Task::factory()->nonBillable()->create()]);

        $this->assertSame(0, (int) $fixed->timeEntries()->sum('amount'));
        $this->assertSame(10_800, $entry->duration_seconds);
        $this->assertSame(0, $entry->amount);
        $this->assertNull($entry->hourly_rate);
    }

    public function test_effective_hourly_rate_exists_only_for_fixed_tasks_with_tracked_time(): void
    {
        $fixed = Task::factory()->fixed(500_000)->create();

        $this->assertSame(71_429, $fixed->effectiveHourlyRate(7 * 3600)); // 5 000 / 7 = 714,29 Kč/h
        $this->assertNull($fixed->effectiveHourlyRate(0));
        $this->assertNull(Task::factory()->create()->effectiveHourlyRate(3600));
    }

    public function test_database_rejects_a_project_of_another_client(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);
        Task::factory()->create(['client_id' => Client::factory(), 'project_id' => $project->id]);
    }

    public function test_database_requires_a_fixed_price_for_fixed_pricing(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('tasks_fixed_price_required');
        Task::factory()->create(['pricing_type' => PricingType::Fixed, 'fixed_price' => null]);
    }

    public function test_moving_to_done_sets_completion_date_and_leaving_done_clears_it(): void
    {
        $task = Task::factory()->create();

        app(MoveTask::class)->handle($task, TaskStatus::Done);
        $this->assertNotNull($task->fresh()?->completed_at);

        $task->refresh()->update(['status' => TaskStatus::Review]);
        $this->assertNull($task->completed_at);
    }

    #[DataProvider('overdueMoments')]
    public function test_overdue_only_after_the_local_due_day(string $now, bool $overdue): void
    {
        $this->travelTo(CarbonImmutable::parse($now, 'Europe/Prague'));
        $task = Task::factory()->create(['due_on' => '2026-10-15']);
        Task::factory()->create(['due_on' => '2026-10-15', 'status' => TaskStatus::Done]);

        $this->assertSame($overdue, $task->isOverdue());
        $this->assertSame($overdue ? [$task->id] : [], Task::query()->overdue()->pluck('id')->all());
    }

    /** @return array<string, array{string, bool}> */
    public static function overdueMoments(): array
    {
        return [
            'on the due day' => ['2026-10-15 23:30', false],
            'just after local midnight (still the 15th in UTC)' => ['2026-10-16 00:30', true],
        ];
    }

    public function test_task_with_time_entries_cannot_move_to_another_project(): void
    {
        $task = Task::factory()->create();
        TimeEntry::factory()->create(['task_id' => $task]);
        $project = Project::factory()->create(['client_id' => $task->client_id]);

        $this->expectException(DomainRuleViolation::class);
        $task->update(['project_id' => $project->id]);
    }

    public function test_changing_pricing_type_re_snapshots_unbilled_entries(): void
    {
        $task = Task::factory()->create();
        $entry = TimeEntry::factory()->lasting(3600)->create(['task_id' => $task]);

        $task->update(['pricing_type' => PricingType::Fixed, 'fixed_price' => 300_000]);

        $entry->refresh();
        $this->assertSame(PricingType::Fixed, $entry->pricing_type);
        $this->assertSame(0, $entry->amount);
    }

    public function test_only_fixed_tasks_can_be_marked_ready_to_bill(): void
    {
        $task = Task::factory()->create();

        $this->expectException(DomainRuleViolation::class);
        $task->markReadyToBill();
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('lockedReadyChanges')]
    public function test_ready_task_locks_its_pricing(array $changes): void
    {
        $task = Task::factory()->fixed(500_000)->create();
        $task->markReadyToBill();

        $this->expectException(DomainRuleViolation::class);
        $task->update($changes);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function lockedReadyChanges(): array
    {
        return [
            'fixed price' => [['fixed_price' => 600_000]],
            'pricing type' => [['pricing_type' => PricingType::Hourly]],
        ];
    }

    public function test_keys_are_numbered_per_client_and_never_reused(): void
    {
        $acme = Client::factory()->create(['code' => 'ACME']);
        $nova = Client::factory()->create(['code' => 'NOVA']);

        $first = Task::factory()->create(['client_id' => $acme]);
        $other = Task::factory()->create(['client_id' => $nova]);
        $second = Task::factory()->forProject(Project::factory()->create(['client_id' => $acme]))->create();
        $second->delete();
        $third = Task::factory()->create(['client_id' => $acme]);

        $this->assertSame(['ACME-1', 'ACME-2', 'NOVA-1', 'ACME-3'], [$first->key(), $second->key(), $other->key(), $third->key()]);
    }

    public function test_task_moved_to_another_client_gets_its_next_number(): void
    {
        $nova = Client::factory()->create(['code' => 'NOVA']);
        Task::factory()->create(['client_id' => $nova]);
        $task = Task::factory()->create();

        $task->update(['client_id' => $nova->id]);

        $this->assertSame('NOVA-2', $task->fresh()?->key());
    }

    public function test_task_is_found_by_its_key_case_insensitively(): void
    {
        $task = Task::factory()->create(['client_id' => Client::factory()->create(['code' => 'ACME'])]);
        Task::factory()->create(['client_id' => Client::factory()->create(['code' => 'NOVA'])]);

        $this->assertSame($task->id, Task::query()->withKey(' acme-1 ')->sole()->id);
        $this->assertFalse(Task::query()->withKey('ACME-2')->exists());
        $this->assertFalse(Task::query()->withKey('not a key')->exists());
    }

    #[DataProvider('clientNames')]
    public function test_suggests_a_free_code_from_the_client_name(string $name, string $code): void
    {
        Client::factory()->create(['code' => 'ACME']);

        $this->assertSame($code, Client::suggestCode($name));
    }

    /** @return array<string, array{string, string}> */
    public static function clientNames(): array
    {
        return [
            'taken code' => ['Acme s.r.o.', 'ACME2'],
            'diacritics' => ['Žluťoučký kůň', 'ZLUT'],
            'leading digit' => ['3D Studio', 'DSTU'],
            'too short' => ['A', 'AX'],
            'no letters' => ['!!!', 'CX'],
        ];
    }

    public function test_ongoing_task_stays_open_but_off_the_board(): void
    {
        $ongoing = Task::factory()->create(['status' => TaskStatus::InProgress, 'is_ongoing' => true]);
        $todo = Task::factory()->create(['status' => TaskStatus::Todo]);

        $this->assertEqualsCanonicalizing([$ongoing->id, $todo->id], Task::query()->open()->pluck('id')->all());
        $this->assertSame([$todo->id], Task::query()->onBoard(doneVisibleDays: 3)->pluck('id')->all());
    }

    public function test_kanban_move_inserts_before_anchor_or_appends_to_column_end(): void
    {
        [$a, $b, $c] = Task::factory()->count(3)->sequence(fn ($s) => ['status' => TaskStatus::Todo, 'position' => $s->index])->create()->all();
        $moved = Task::factory()->create(['status' => TaskStatus::Backlog]);

        app(MoveTask::class)->handle($moved, TaskStatus::Todo, before: $b);
        app(MoveTask::class)->handle($a, TaskStatus::Todo);

        $this->assertSame(
            [$moved->id, $b->id, $c->id, $a->id],
            Task::query()->where('status', TaskStatus::Todo)->orderBy('position')->pluck('id')->all(),
        );
    }
}
