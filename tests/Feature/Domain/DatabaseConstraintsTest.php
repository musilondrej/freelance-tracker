<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * These bypass the models on purpose: the database is the last line of defence.
 */
final class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  Closure(): mixed  $violate
     */
    #[DataProvider('violations')]
    public function test_database_rejects_invalid_data(Closure $violate, ?string $constraint): void
    {
        $this->expectException(QueryException::class);

        if ($constraint !== null) {
            $this->expectExceptionMessage($constraint);
        }

        $violate();
    }

    /**
     * @return array<string, array{Closure(): mixed, ?string}>
     */
    public static function violations(): array
    {
        return [
            'finished entry without a snapshot' => [
                fn (): bool => DB::table('time_entries')->insert([
                    'task_id' => Task::factory()->create()->id,
                    'started_at' => now()->subHour(),
                    'ended_at' => now(),
                ]),
                'time_entries_snapshot_when_finished',
            ],
            'hourly snapshot without a rate' => [
                fn (): bool => DB::table('time_entries')->insert([
                    'task_id' => Task::factory()->create()->id,
                    'started_at' => now()->subHour(),
                    'ended_at' => now(),
                    'pricing_type' => 'hourly',
                    'currency' => 'CZK',
                ]),
                'time_entries_rate_only_for_hourly',
            ],
            'ready to bill on a non-fixed task' => [
                fn (): int => DB::table('tasks')->where('id', Task::factory()->create()->id)->update(['ready_to_bill_at' => now()]),
                'tasks_ready_to_bill_fixed_only',
            ],
            'sent statement without a sent date' => [
                fn (): bool => DB::table('statements')->insert(['client_id' => Client::factory()->create()->id, 'currency' => 'CZK', 'status' => 'sent']),
                'statements_sent_has_date',
            ],
            'zero client rate' => [
                fn (): Client => Client::factory()->create(['hourly_rate' => 0]),
                'clients_hourly_rate_check',
            ],
            'zero fixed price' => [
                fn (): Task => Task::factory()->fixed(0)->create(),
                'tasks_fixed_price_check',
            ],
            'deleting a client with history' => [
                fn (): ?bool => Task::factory()->create()->client->delete(),
                null,
            ],
            'note about a project of another client' => [
                fn (): bool => DB::table('notes')->insert([
                    'client_id' => Client::factory()->create()->id,
                    'project_id' => Project::factory()->create()->id,
                    'noted_on' => '2026-10-11',
                    'body' => 'Wants a PDF export',
                ]),
                'notes_project_client_foreign',
            ],
            'exchange rate of CZK' => [
                fn (): bool => DB::table('exchange_rates')->insert(['rate_on' => '2026-10-09', 'currency' => 'CZK', 'amount' => 1, 'rate' => 1_000]),
                'exchange_rates_currency_check',
            ],
            'link that is not http' => [
                fn (): bool => DB::table('links')->insert(['client_id' => Client::factory()->create()->id, 'url' => 'javascript:alert(1)']),
                'links_url_check',
            ],
            'lower-case client code' => [
                fn (): int => DB::table('clients')->where('id', Client::factory()->create()->id)->update(['code' => 'acme']),
                'clients_code_format',
            ],
            'duplicate task number per client' => [
                function (): int {
                    $task = Task::factory()->create();
                    $other = Task::factory()->create(['client_id' => $task->client_id]);

                    return DB::table('tasks')->where('id', $other->id)->update(['number' => $task->number]);
                },
                'tasks_client_id_number_unique',
            ],
        ];
    }
}
