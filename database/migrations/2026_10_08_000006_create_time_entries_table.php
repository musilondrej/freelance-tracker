<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $seconds = 'EXTRACT(EPOCH FROM (ended_at - started_at))';

        Schema::create('time_entries', function (Blueprint $table) use ($seconds): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->text('note')->nullable();

            // Snapshot taken when the entry is finished. NULL while the timer runs.
            $table->enum('pricing_type', ['hourly', 'fixed', 'non_billable'])->nullable();
            $table->bigInteger('hourly_rate')->nullable();
            $table->char('currency', 3)->nullable();

            // Derived by the database so they can never drift from the times and the snapshot.
            // Postgres does not let a generated column use another one, so the expression repeats.
            $table->integer('duration_seconds')->nullable()->storedAs("({$seconds})::integer");
            // Value of work in minor units: seconds * rate / 3600, rounded half up. Same rule as Money::roundHalfUpDiv().
            $table->bigInteger('amount')->nullable()->storedAs(
                "CASE WHEN ended_at IS NULL THEN NULL
                      WHEN pricing_type = 'hourly' THEN (2 * ({$seconds})::bigint * hourly_rate + 3600) / 7200
                      ELSE 0 END"
            );

            $table->timestampsTz();

            $table->index('task_id');
            $table->index('started_at');
        });

        // At most one running timer.
        DB::statement('CREATE UNIQUE INDEX time_entries_one_running ON time_entries ((true)) WHERE ended_at IS NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE time_entries
                ADD CONSTRAINT time_entries_positive_duration CHECK (ended_at IS NULL OR ended_at > started_at),
                ADD CONSTRAINT time_entries_snapshot_when_finished CHECK ((ended_at IS NULL) = (pricing_type IS NULL) AND (ended_at IS NULL) = (currency IS NULL)),
                ADD CONSTRAINT time_entries_rate_only_for_hourly CHECK ((pricing_type = 'hourly') = (hourly_rate IS NOT NULL)),
                ADD CONSTRAINT time_entries_hourly_rate_check CHECK (hourly_rate > 0),
                ADD CONSTRAINT time_entries_currency_check CHECK (currency ~ '^[A-Z]{3}$')
            SQL);
    }
};
