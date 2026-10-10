<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AlertsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_and_switches_off_alert_preferences_on_the_profile_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['timer_alert_seconds' => '2h 30m', 'recurring_reminder_days' => 14, 'notify_by_mail' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame(9000, $user->timer_alert_seconds);
        $this->assertSame(14, $user->recurring_reminder_days);
        $this->assertFalse($user->notify_by_mail);

        Livewire::test(EditProfile::class)
            ->fillForm(['timer_alert_seconds' => null, 'recurring_reminder_days' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($user->refresh()->timer_alert_seconds);
        $this->assertNull($user->refresh()->recurring_reminder_days);
    }
}
