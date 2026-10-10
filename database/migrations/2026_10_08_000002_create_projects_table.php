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
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->bigInteger('hourly_rate')->nullable();
            $table->bigInteger('budget')->nullable();
            $table->integer('estimated_seconds')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            // Target of the tasks (project_id, client_id) foreign key.
            $table->unique(['id', 'client_id']);
            $table->index('client_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE projects
                ADD CONSTRAINT projects_hourly_rate_check CHECK (hourly_rate > 0),
                ADD CONSTRAINT projects_budget_check CHECK (budget >= 0),
                ADD CONSTRAINT projects_estimated_seconds_check CHECK (estimated_seconds > 0)
            SQL);
    }
};
