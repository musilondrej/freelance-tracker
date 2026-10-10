<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Actions\ExchangeRates\FetchCnbRates;
use App\Filament\Widgets\FinanceOverview;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Reporting\CzkTotal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    }

    public function test_stores_the_cnb_fixing_of_the_apps_currencies_once(): void
    {
        Http::fake([FetchCnbRates::URL => Http::response("09.10.2026 #195\nzemě|měna|množství|kód|kurz\nEMU|euro|1|EUR|24,365\nJaponsko|jen|100|JPY|13,744\nUSA|dolar|1|USD|20,912\n")]);

        $this->assertSame(1, app(FetchCnbRates::class)->handle());
        app(FetchCnbRates::class)->handle();

        $rate = ExchangeRate::query()->sole();
        $this->assertSame(['2026-10-09', 'EUR', 1, 24_365], [$rate->rate_on->toDateString(), $rate->currency->value, $rate->amount, $rate->rate]);
    }

    public function test_converts_with_the_newest_rate_up_to_today(): void
    {
        ExchangeRate::query()->create(['rate_on' => '2026-10-01', 'currency' => 'EUR', 'amount' => 1, 'rate' => 25_000]);
        ExchangeRate::query()->create(['rate_on' => '2026-10-14', 'currency' => 'EUR', 'amount' => 1, 'rate' => 24_365]);
        ExchangeRate::query()->create(['rate_on' => '2026-10-16', 'currency' => 'EUR', 'amount' => 1, 'rate' => 1_000]);

        $total = CzkTotal::of(['CZK' => 420_000, 'EUR' => 81_050]);

        // 810,50 € × 24,365 = 19 747,83 Kč, rounded half up.
        $this->assertNotNull($total);
        $this->assertSame(420_000 + 1_974_783, $total->amount);
        $this->assertSame('2026-10-14', $total->rateOn->toDateString());
    }

    public function test_without_a_rate_nothing_is_converted(): void
    {
        $this->assertNull(CzkTotal::of(['CZK' => 100, 'EUR' => 100]));
        $this->assertSame(100, CzkTotal::of(['CZK' => 100])?->amount);
    }

    public function test_overview_shows_one_approximate_czk_figure_with_the_original_amounts(): void
    {
        ExchangeRate::query()->create(['rate_on' => '2026-10-14', 'currency' => 'EUR', 'amount' => 1, 'rate' => 25_000]);
        TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->create(['client_id' => Client::factory()->create(['hourly_rate' => 120_000])])]);
        TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->create(['client_id' => Client::factory()->eur()->create(['hourly_rate' => 6_000])])]);
        $this->actingAs(User::factory()->create());

        // 1 200 Kč + 60 € × 25 = 2 700 Kč.
        Livewire::test(FinanceOverview::class)
            ->assertSee("2\u{00A0}700\u{00A0}Kč")
            ->assertSee("1\u{00A0}200\u{00A0}Kč + 60\u{00A0}€");

        $this->get('/')->assertSee(__('Totals in several currencies are converted to CZK at the ČNB rate of :date.', ['date' => '14. 10. 2026']));
    }
}
