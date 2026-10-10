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
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            // With the client code it makes the task key, like ACME-12.
            $table->integer('number');
            $table->foreignId('project_id')->nullable();
            $table->foreignId('recurring_task_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['backlog', 'todo', 'in_progress', 'waiting', 'review', 'done'])->default('todo');
            $table->unsignedSmallInteger('priority')->default(2);
            $table->enum('pricing_type', ['hourly', 'fixed', 'non_billable'])->default('hourly');
            $table->bigInteger('hourly_rate')->nullable();
            $table->bigInteger('fixed_price')->nullable();
            $table->integer('estimated_seconds')->nullable();
            $table->date('due_on')->nullable();
            $table->integer('position')->default(0);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('ready_to_bill_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            // A task's project must belong to the same client. Not checked when project_id is NULL.
            $table->foreign(['project_id', 'client_id'])->references(['id', 'client_id'])->on('projects')->restrictOnDelete();

            $table->unique(['client_id', 'number']);
            // Target of composite foreign keys that keep a record on the task's client.
            $table->unique(['id', 'client_id']);
            // One task per template and period, even if the generator runs twice at once.
            $table->unique(['recurring_task_id', 'period_start']);
            $table->index('project_id');
            $table->index(['status', 'position']);
            $table->index('due_on');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tasks
                ADD CONSTRAINT tasks_number_check CHECK (number > 0),
                ADD CONSTRAINT tasks_priority_check CHECK (priority BETWEEN 1 AND 4),
                ADD CONSTRAINT tasks_hourly_rate_check CHECK (hourly_rate > 0),
                ADD CONSTRAINT tasks_fixed_price_check CHECK (fixed_price > 0),
                ADD CONSTRAINT tasks_estimated_seconds_check CHECK (estimated_seconds > 0),
                ADD CONSTRAINT tasks_fixed_price_required CHECK (pricing_type <> 'fixed' OR fixed_price IS NOT NULL),
                ADD CONSTRAINT tasks_ready_to_bill_fixed_only CHECK (ready_to_bill_at IS NULL OR pricing_type = 'fixed'),
                ADD CONSTRAINT tasks_period_check CHECK ((period_start IS NULL) = (period_end IS NULL) AND (period_end IS NULL OR period_end >= period_start))
            SQL);
    }
};
