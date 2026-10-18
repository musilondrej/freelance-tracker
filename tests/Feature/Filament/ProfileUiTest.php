<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Actions\Billing\CreateStatement;
use App\Enums\Currency;
use App\Enums\Locale;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ProfileUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_prefill_new_clients(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(EditProfile::class)
            ->fillForm(['default_currency' => Currency::EUR, 'default_hourly_rate' => '65,50', 'workday_hours' => 6])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditProfile::class)
            ->fillForm(['default_hourly_rate' => '0,00'])
            ->call('save')
            ->assertHasFormErrors(['default_hourly_rate']);

        $user->refresh();
        $this->assertSame([Currency::EUR, 6_550, 6], [$user->default_currency, $user->default_hourly_rate, $user->workday_hours]);

        Livewire::test(CreateClient::class)->assertSchemaStateSet(['currency' => Currency::EUR, 'hourly_rate' => '65,50']);
    }

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

    public function test_statement_pdf_prints_the_supplier_only_when_named(): void
    {
        $entry = TimeEntry::factory()->lasting(3600)->create();
        $statement = app(CreateStatement::class)->handle($entry->task->client, [$entry->task]);
        $render = fn (User $supplier): string => view('pdf.statement', ['statement' => $statement, 'supplier' => $supplier])->render();

        $this->assertStringNotContainsString(__('Supplier'), $render(User::factory()->create()));

        $html = $render(User::factory()->create(['billing_name' => 'Jan Novák', 'billing_bank_account' => '123456789/0100']));
        $this->assertStringContainsString('Jan Novák', $html);
        $this->assertStringContainsString('123456789/0100', $html);
    }

    public function test_ui_follows_the_users_language(): void
    {
        $this->actingAs($user = User::factory()->create());
        $this->get('/profile')->assertSee('Jazyk');

        $user->update(['locale' => Locale::English]);
        $this->get('/profile')->assertSee('Language')->assertDontSee('Jazyk');
    }
}
