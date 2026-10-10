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
        Schema::create('recurring_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('priority')->default(2);
            $table->enum('pricing_type', ['hourly', 'fixed', 'non_billable'])->default('fixed');
            $table->bigInteger('hourly_rate')->nullable();
            $table->bigInteger('fixed_price')->nullable();
            $table->integer('estimated_seconds')->nullable();
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly']);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->enum('billing_timing', ['in_advance', 'in_arrears'])->default('in_arrears');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            // Number of periods already generated. Period k starts at starts_on + k * months * interval.
            $table->unsignedInteger('periods_generated')->default(0);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['project_id', 'client_id'])->references(['id', 'client_id'])->on('projects')->restrictOnDelete();
            $table->index('client_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE recurring_tasks
                ADD CONSTRAINT recurring_tasks_priority_check CHECK (priority BETWEEN 1 AND 4),
                ADD CONSTRAINT recurring_tasks_interval_check CHECK (interval BETWEEN 1 AND 24),
                ADD CONSTRAINT recurring_tasks_money_check CHECK (hourly_rate > 0 AND fixed_price > 0),
                ADD CONSTRAINT recurring_tasks_fixed_price_required CHECK (pricing_type <> 'fixed' OR fixed_price IS NOT NULL),
                ADD CONSTRAINT recurring_tasks_period_check CHECK (ends_on IS NULL OR ends_on >= starts_on)
            SQL);
    }
};
