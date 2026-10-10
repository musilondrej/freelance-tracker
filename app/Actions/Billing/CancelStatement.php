<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\StatementStatus;
use App\Models\Statement;
use Illuminate\Support\Facades\DB;

final class CancelStatement
{
    /**
     * Cancels a sent statement. Its time entries can be billed again.
     * Its items stay as history but no longer count in any stats.
     */
    public function handle(Statement $statement): Statement
    {
        return DB::transaction(function () use ($statement): Statement {
            $statement = $statement->lockInStatus(StatementStatus::Sent);

            DB::table('statement_item_time_entry')
                ->whereIn('statement_item_id', $statement->items()->select('id'))
                ->delete();

            $statement->forceFill(['status' => StatementStatus::Cancelled])->save();

            return $statement;
        });
    }
}
