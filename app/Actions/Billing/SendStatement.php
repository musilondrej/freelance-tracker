<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\StatementStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Statement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SendStatement
{
    /** Marks the work as billed. From now on the items are immutable history. */
    public function handle(Statement $statement, CarbonImmutable $sentOn): Statement
    {
        return DB::transaction(function () use ($statement, $sentOn): Statement {
            $statement = $statement->lockInStatus(StatementStatus::Draft);

            if (! $statement->items()->exists()) {
                throw DomainRuleViolation::because('An empty statement cannot be sent.');
            }

            $statement->forceFill(['status' => StatementStatus::Sent, 'sent_on' => $sentOn])->save();

            return $statement;
        });
    }
}
