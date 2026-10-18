<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Actions\TimeTracking\StartTimer;
use App\Actions\TimeTracking\StopTimer;
use App\Exceptions\DomainRuleViolation;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TimerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
    }

    public function test_starts_on_server_time_and_snapshots_the_rate_on_stop(): void
    {
        $task = Task::factory()->create(['hourly_rate' => 100_000]);

        $running = app(StartTimer::class)->handle($task);

        $this->assertTrue($running->isRunning());
        $this->assertTrue($running->started_at->equalTo(now()));
        $this->assertNull($running->pricing_type);
        $this->assertNull($running->amount);

        $this->travel(90)->minutes();
        $entry = app(StopTimer::class)->handle();

        $this->assertNotNull($entry);
        $this->assertSame(5400, $entry->duration_seconds);
        $this->assertSame(100_000, $entry->hourly_rate);
        $this->assertSame(150_000, $entry->amount);
    }

    public function test_refuses_a_second_running_timer(): void
    {
        app(StartTimer::class)->handle(Task::factory()->create());

        $this->expectException(DomainRuleViolation::class);
        $this->expectExceptionMessage(__('A timer is already running. Stop it first.'));

        app(StartTimer::class)->handle(Task::factory()->create());
    }

    public function test_database_allows_only_one_running_timer(): void
    {
        TimeEntry::factory()->running()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        TimeEntry::factory()->running()->create();
    }

    public function test_switches_the_timer_atomically_when_asked_to(): void
    {
        $first = Task::factory()->create();
        $second = Task::factory()->create();
        app(StartTimer::class)->handle($first);
        $this->travel(10)->minutes();

        $entry = app(StartTimer::class)->handle($second, stopRunning: true);

        $this->assertTrue(TimeEntry::query()->running()->sole()->is($entry));
        $this->assertSame(600, TimeEntry::query()->where('task_id', $first->id)->sole()->duration_seconds);
    }

    public function test_back_dated_switch_ends_the_previous_timer_at_the_new_start(): void
    {
        $first = Task::factory()->create();
        app(StartTimer::class)->handle($first);
        $this->travel(60)->minutes();

        $entry = app(StartTimer::class)->handle(Task::factory()->create(), stopRunning: true, startedAt: now()->subMinutes(20));

        $this->assertSame(1200, $entry->elapsedSeconds());
        $this->assertSame(2400, TimeEntry::query()->where('task_id', $first->id)->sole()->duration_seconds);
    }

    public function test_refuses_a_start_in_the_future_or_before_the_running_timer(): void
    {
        $task = Task::factory()->create();

        try {
            app(StartTimer::class)->handle($task, startedAt: now()->addMinute());
            $this->fail('A future start was accepted.');
        } catch (DomainRuleViolation) {
        }

        app(StartTimer::class)->handle($task);

        $this->expectException(DomainRuleViolation::class);
        $this->expectExceptionMessage(__('The new start is before the running timer started.'));

        app(StartTimer::class)->handle($task, stopRunning: true, startedAt: now()->subMinute());
    }

    public function test_discards_a_timer_stopped_within_the_same_second(): void
    {
        app(StartTimer::class)->handle(Task::factory()->create());

        $this->assertNull(app(StopTimer::class)->handle());
        $this->assertSame(0, TimeEntry::query()->count());

        $this->expectException(DomainRuleViolation::class);

        app(StopTimer::class)->handle(); // nothing runs any more
    }

    public function test_does_not_track_time_on_archived_tasks(): void
    {
        $task = Task::factory()->create();
        $task->archive();

        $this->expectException(DomainRuleViolation::class);

        app(StartTimer::class)->handle($task);
    }

    public function test_cannot_resume_a_finished_entry(): void
    {
        $entry = TimeEntry::factory()->create();

        $this->expectException(DomainRuleViolation::class);

        $entry->update(['ended_at' => null]);
    }

    #[DataProvider('nonPositiveDurations')]
    public function test_rejects_zero_and_negative_durations(int $seconds): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('time_entries_positive_duration');

        TimeEntry::factory()->lasting($seconds, now()->subDay())->create();
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveDurations(): array
    {
        return [
            'zero' => [0],
        ];
    }
}
