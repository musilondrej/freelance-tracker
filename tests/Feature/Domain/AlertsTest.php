<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Actions\Alerts\AlertForgottenTimer;
use App\Actions\Alerts\RemindUpcomingDeadlines;
use App\Actions\Alerts\RemindUpcomingRecurring;
use App\Actions\TimeTracking\StartTimer;
use App\Enums\BillingTiming;
use App\Enums\TaskStatus;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DeadlinesApproaching;
use App\Notifications\RecurringPeriodUpcoming;
use App\Notifications\TimerRunningTooLong;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Europe/Prague'));
        Notification::fake();
    }

    public function test_forgotten_timer_alerts_once_per_timer_after_the_limit(): void
    {
        $user = User::factory()->create(['timer_alert_seconds' => 4 * 3600]);
        app(StartTimer::class)->handle(Task::factory()->create(['title' => 'API']));

        $this->travel(3)->hours();
        $this->assertSame(0, app(AlertForgottenTimer::class)->handle());

        $this->travel(61)->minutes();
        $this->assertSame(1, app(AlertForgottenTimer::class)->handle());
        $this->assertSame(0, app(AlertForgottenTimer::class)->handle());
        Notification::assertSentToTimes($user, TimerRunningTooLong::class, 1);
        Notification::assertSentTo($user, TimerRunningTooLong::class, fn ($n, array $channels) => $channels === ['database', 'mail']);

        app(StartTimer::class)->handle(Task::factory()->create(), stopRunning: true);
        $this->travel(5)->hours();
        app(AlertForgottenTimer::class)->handle();

        Notification::assertSentToTimes($user, TimerRunningTooLong::class, 2);
    }

    public function test_forgotten_timer_respects_switched_off_alerts_and_mail_preference(): void
    {
        $off = User::factory()->create(['timer_alert_seconds' => null]);
        $inApp = User::factory()->create(['timer_alert_seconds' => 3600, 'notify_by_mail' => false]);
        app(StartTimer::class)->handle(Task::factory()->create());
        $this->travel(2)->hours();

        app(AlertForgottenTimer::class)->handle();

        Notification::assertNotSentTo($off, TimerRunningTooLong::class);
        Notification::assertSentTo($inApp, TimerRunningTooLong::class, fn ($n, array $channels) => $channels === ['database']);
    }

    public function test_upcoming_period_is_reminded_once_and_only_within_the_window(): void
    {
        $user = User::factory()->create(['recurring_reminder_days' => 7]);
        RecurringTask::factory()->create(['starts_on' => '2026-10-20', 'billing_timing' => BillingTiming::InAdvance]);
        RecurringTask::factory()->create(['starts_on' => '2026-11-01']);
        RecurringTask::factory()->create(['starts_on' => '2026-10-15']);
        RecurringTask::factory()->create(['starts_on' => '2026-10-18'])->archive();

        $this->assertSame(1, app(RemindUpcomingRecurring::class)->handle());
        $this->assertSame(0, app(RemindUpcomingRecurring::class)->handle());
        Notification::assertSentToTimes($user, RecurringPeriodUpcoming::class, 1);
    }

    public function test_reminds_of_the_next_period_after_generation(): void
    {
        $user = User::factory()->create(['recurring_reminder_days' => 20]);
        RecurringTask::factory()->create(['starts_on' => '2026-10-01'])->forceFill(['periods_generated' => 1])->save();

        app(RemindUpcomingRecurring::class)->handle();

        Notification::assertSentTo(
            $user,
            RecurringPeriodUpcoming::class,
            fn (RecurringPeriodUpcoming $notification): bool => str_contains($notification->toMail($user)->subject, '1. 11. 2026'),
        );
    }

    public function test_deadline_warning_is_one_digest_and_announces_each_deadline_once(): void
    {
        $user = User::factory()->create(['deadline_reminder_days' => 2]);
        $today = Task::factory()->create(['title' => 'Today', 'due_on' => '2026-10-15']);
        Task::factory()->create(['title' => 'In two days', 'due_on' => '2026-10-17']);
        Task::factory()->create(['title' => 'Later', 'due_on' => '2026-10-18']);
        Task::factory()->create(['title' => 'Done', 'due_on' => '2026-10-16', 'status' => TaskStatus::Done]);
        Task::factory()->create(['title' => 'Archived', 'due_on' => '2026-10-16'])->archive();

        $this->assertSame(1, app(RemindUpcomingDeadlines::class)->handle());
        Notification::assertSentTo($user, DeadlinesApproaching::class, fn (DeadlinesApproaching $notification): bool => str_contains(
            (string) json_encode($notification->toDatabase($user)), 'In two days',
        ) && ! str_contains((string) json_encode($notification->toDatabase($user)), 'Later'));

        // Already announced: nothing new the same day or the next.
        $this->assertSame(0, app(RemindUpcomingDeadlines::class)->handle());

        // A moved deadline is announced again.
        $today->update(['due_on' => '2026-10-16']);
        $this->assertSame(1, app(RemindUpcomingDeadlines::class)->handle());
    }

    public function test_deadline_warning_can_be_switched_off(): void
    {
        User::factory()->create(['deadline_reminder_days' => null]);
        Task::factory()->create(['due_on' => '2026-10-15']);

        $this->assertSame(0, app(RemindUpcomingDeadlines::class)->handle());
        Notification::assertNothingSent();
    }
}
