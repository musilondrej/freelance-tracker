<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Actions\Billing\AddTasksToStatement;
use App\Actions\Billing\AddTimeEntriesToStatement;
use App\Actions\Billing\CancelStatement;
use App\Actions\Billing\CreateStatement;
use App\Actions\Billing\SendStatement;
use App\Enums\Currency;
use App\Enums\PricingType;
use App\Enums\StatementStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Client;
use App\Models\Statement;
use App\Models\StatementItem;
use App\Models\Task;
use App\Models\TimeEntry;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BillingTest extends TestCase
{
    use RefreshDatabase;

    // Midday UTC: cut-offs at the start of the day never fall between the test's entries.
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    }

    public function test_creates_a_draft_with_all_unbilled_hourly_entries_and_the_fixed_price(): void
    {
        $client = Client::factory()->create();
        $hourly = Task::factory()->create(['client_id' => $client]);
        TimeEntry::factory()->count(5)->lasting(3600)->create(['task_id' => $hourly]);
        $fixed = Task::factory()->fixed(400_000)->create(['client_id' => $client]);
        $fixed->markReadyToBill();

        $statement = app(CreateStatement::class)->handle($client, [$hourly, $fixed]);

        $this->assertSame(StatementStatus::Draft, $statement->status);
        $this->assertSame(Currency::CZK, $statement->currency);
        $this->assertEquals(1_000_000, $statement->items()->sum('amount'));
        $this->assertSame(
            [PricingType::Fixed, PricingType::Hourly],
            $statement->items()->get()->map(fn (StatementItem $i) => $i->pricing_type)->sortBy('value')->values()->all(),
        );
        $this->assertSame(0, $hourly->timeEntries()->unallocated()->count());
    }

    public function test_groups_hourly_entries_into_one_item_per_task_and_rate(): void
    {
        $task = Task::factory()->create();
        TimeEntry::factory()->lasting(3600)->create(['task_id' => $task]);
        $task->update(['hourly_rate' => 150_000]);
        TimeEntry::factory()->count(2)->lasting(1800)->create(['task_id' => $task]);

        $statement = app(CreateStatement::class)->handle($task->client, [$task]);

        $this->assertEqualsCanonicalizing(
            [[120_000, 3600, 120_000], [150_000, 3600, 150_000]],
            $statement->items()->get()->map(fn (StatementItem $i) => [$i->unit_price, $i->billed_seconds, $i->amount])->all(),
        );
    }

    public function test_partial_billing_by_cut_off_date_and_by_selected_entries(): void
    {
        $task = Task::factory()->create();
        $old = TimeEntry::factory()->lasting(3600, now()->subMonth()->toImmutable())->create(['task_id' => $task]);
        $new = TimeEntry::factory()->lasting(3600, now()->subHours(2)->toImmutable())->create(['task_id' => $task]);
        $later = TimeEntry::factory()->lasting(3600, now()->subHour()->toImmutable())->create(['task_id' => $task]);

        $statement = app(CreateStatement::class)->handle($task->client, [$task], until: now()->startOfDay()->toImmutable());

        $this->assertTrue($old->isAllocated());
        $this->assertFalse($new->isAllocated());

        app(AddTimeEntriesToStatement::class)->handle($statement, [$new->id]);

        $this->assertFalse($later->isAllocated());
        $this->assertSame(240_000, $statement->items()->sole()->amount);
    }

    public function test_an_entry_cannot_be_billed_twice(): void
    {
        $entry = TimeEntry::factory()->create();
        $client = $entry->task->client;
        $statement = app(CreateStatement::class)->handle($client);
        app(AddTimeEntriesToStatement::class)->handle($statement, [$entry->id]);

        $this->assertThrows(
            fn () => app(AddTimeEntriesToStatement::class)->handle(app(CreateStatement::class)->handle($client), [$entry->id]),
            DomainRuleViolation::class,
        );

        // Concurrent allocation is stopped by the UNIQUE pivot. Must be last: the transaction aborts.
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('statement_item_time_entry')->insert([
            'statement_item_id' => $statement->items()->sole()->id,
            'time_entry_id' => $entry->id,
        ]);
    }

    /** @param Closure(Client): TimeEntry $makeEntry */
    #[DataProvider('ineligibleEntries')]
    public function test_refuses_ineligible_entries(Closure $makeEntry): void
    {
        $client = Client::factory()->create();
        $statement = app(CreateStatement::class)->handle($client);
        $entry = $makeEntry($client);

        $this->expectException(DomainRuleViolation::class);
        app(AddTimeEntriesToStatement::class)->handle($statement, [$entry->id]);
    }

    /** @return array<string, array{Closure(Client): TimeEntry}> */
    public static function ineligibleEntries(): array
    {
        return [
            'other client' => [fn (Client $c): TimeEntry => TimeEntry::factory()->create()],
            'running' => [fn (Client $c): TimeEntry => TimeEntry::factory()->running()->create(['task_id' => Task::factory()->create(['client_id' => $c])])],
            'fixed' => [fn (Client $c): TimeEntry => TimeEntry::factory()->create(['task_id' => Task::factory()->fixed(1000)->create(['client_id' => $c])])],
            'non-billable' => [fn (Client $c): TimeEntry => TimeEntry::factory()->create(['task_id' => Task::factory()->nonBillable()->create(['client_id' => $c])])],
        ];
    }

    public function test_bills_a_fixed_task_once_at_its_whole_price(): void
    {
        $task = Task::factory()->fixed(1_000_000)->create();
        $task->markReadyToBill();
        $statement = app(CreateStatement::class)->handle($task->client, [$task]);

        $this->assertSame(1_000_000, $statement->items()->sole()->amount);
        $this->assertTrue($task->isFixedPriceBilled());

        $this->expectException(DomainRuleViolation::class);
        app(AddTasksToStatement::class)->handle($statement, [$task]);
    }

    public function test_fixed_task_must_be_ready_to_bill_and_cannot_be_unmarked_once_billed(): void
    {
        $task = Task::factory()->fixed(100_000)->create();

        $this->assertThrows(fn () => app(CreateStatement::class)->handle($task->client, [$task]), DomainRuleViolation::class);

        $task->markReadyToBill();
        app(CreateStatement::class)->handle($task->client, [$task]);

        $this->expectException(DomainRuleViolation::class);
        $task->unmarkReadyToBill();
    }

    public function test_send_and_cancel_require_the_right_state(): void
    {
        $empty = app(CreateStatement::class)->handle(Client::factory()->create());
        $this->assertThrows(fn () => $this->send($empty), DomainRuleViolation::class);
        $this->assertThrows(fn () => app(CancelStatement::class)->handle($empty), DomainRuleViolation::class);

        $entry = TimeEntry::factory()->create();
        $statement = $this->send(app(CreateStatement::class)->handle($entry->task->client, [$entry->task]));

        $this->assertSame(StatementStatus::Sent, $statement->status);
        $this->assertNotNull($statement->sent_on);
    }

    public function test_billed_entries_are_frozen(): void
    {
        $entry = TimeEntry::factory()->create();
        app(CreateStatement::class)->handle($entry->task->client, [$entry->task]);

        $this->assertThrows(fn () => $entry->update(['note' => 'x']), DomainRuleViolation::class);
        $this->assertThrows(fn () => $entry->delete(), DomainRuleViolation::class);

        $entry->task->update(['hourly_rate' => 200_000]);
        $this->assertSame(0, $entry->task->recalculateTimeEntries());
        $this->assertSame(120_000, $entry->fresh()?->amount);
    }

    public function test_deleting_a_draft_releases_its_entries(): void
    {
        $entry = TimeEntry::factory()->create();
        $statement = app(CreateStatement::class)->handle($entry->task->client, [$entry->task]);

        $statement->delete();

        $this->assertFalse($entry->isAllocated());
        $this->assertSame(0, StatementItem::query()->count());
    }

    public function test_sent_statements_cannot_be_deleted_or_edited(): void
    {
        $entry = TimeEntry::factory()->create();
        $statement = $this->send(app(CreateStatement::class)->handle($entry->task->client, [$entry->task]));

        $this->assertThrows(fn () => $statement->delete(), DomainRuleViolation::class);
        $this->assertThrows(fn () => $statement->items()->sole()->update(['description' => 'x']), DomainRuleViolation::class);
        $this->assertThrows(fn () => $statement->update(['note' => 'x']), DomainRuleViolation::class);
    }

    public function test_cancelling_a_sent_statement_releases_work_but_keeps_history(): void
    {
        $client = Client::factory()->create();
        $entry = TimeEntry::factory()->create(['task_id' => Task::factory()->create(['client_id' => $client])]);
        $fixed = Task::factory()->fixed(100_000)->create(['client_id' => $client]);
        $fixed->markReadyToBill();
        $statement = $this->send(app(CreateStatement::class)->handle($client, [$entry->task, $fixed]));

        app(CancelStatement::class)->handle($statement);

        $this->assertSame(StatementStatus::Cancelled, $statement->fresh()?->status);
        $this->assertEquals(220_000, $statement->items()->sum('amount'));
        $this->assertFalse($entry->isAllocated());
        $this->assertFalse($fixed->isFixedPriceBilled());

        $again = app(CreateStatement::class)->handle($client, [$entry->task, $fixed]);
        $this->assertEquals(220_000, $again->items()->sum('amount'));
    }

    public function test_client_currency_is_locked_once_there_is_financial_history(): void
    {
        $fresh = Client::factory()->create();
        $fresh->update(['currency' => Currency::EUR]);
        $this->assertSame(Currency::EUR, $fresh->fresh()?->currency);

        $entry = TimeEntry::factory()->create();
        $this->expectException(DomainRuleViolation::class);
        $entry->task->client->update(['currency' => Currency::EUR]);
    }

    public function test_statement_currency_must_match_the_client_at_the_database_level(): void
    {
        $client = Client::factory()->create();

        $this->expectException(QueryException::class);
        (new Statement)->forceFill(['client_id' => $client->id, 'currency' => 'EUR'])->save();
    }

    private function send(Statement $statement): Statement
    {
        return app(SendStatement::class)->handle($statement, now()->toImmutable());
    }
}
