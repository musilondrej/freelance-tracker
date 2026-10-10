<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Client;
use App\Models\Statement;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CreateStatement
{
    public function __construct(private AddTasksToStatement $addTasks) {}

    /**
     * @param  iterable<Task>  $tasks
     */
    public function handle(Client $client, iterable $tasks = [], ?CarbonImmutable $until = null, ?string $note = null): Statement
    {
        return DB::transaction(function () use ($client, $tasks, $until, $note): Statement {
            $statement = new Statement;
            $statement->forceFill(['client_id' => $client->id, 'currency' => $client->currency, 'note' => $note])->save();

            $tasks = collect($tasks);

            if ($tasks->isNotEmpty()) {
                $this->addTasks->handle($statement, $tasks, $until);
            }

            return $statement;
        });
    }
}
