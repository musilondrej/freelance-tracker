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
        Schema::create('statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id');
            $table->char('currency', 3);
            $table->enum('status', ['draft', 'sent', 'cancelled'])->default('draft');
            $table->date('sent_on')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            // The statement currency is always the client's currency. This also blocks changing it once statements exist.
            $table->foreign(['client_id', 'currency'])->references(['id', 'currency'])->on('clients')->restrictOnDelete();
            $table->index('client_id');
        });

        DB::statement("ALTER TABLE statements ADD CONSTRAINT statements_sent_has_date CHECK ((status = 'draft') = (sent_on IS NULL))");

        Schema::create('statement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->enum('pricing_type', ['hourly', 'fixed']);
            $table->bigInteger('unit_price')->nullable();
            $table->integer('billed_seconds')->default(0);
            $table->bigInteger('amount');
            $table->integer('position')->default(0);
            $table->timestampsTz();

            $table->index('statement_id');
            $table->index('task_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE statement_items
                ADD CONSTRAINT statement_items_amount_check CHECK (amount > 0),
                ADD CONSTRAINT statement_items_billed_seconds_check CHECK (billed_seconds >= 0),
                ADD CONSTRAINT statement_items_unit_price_for_hourly CHECK ((pricing_type = 'hourly') = (unit_price IS NOT NULL))
            SQL);

        // Allocation of time entries to hourly items. UNIQUE(time_entry_id) prevents double billing.
        Schema::create('statement_item_time_entry', function (Blueprint $table): void {
            $table->foreignId('statement_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->unique()->constrained()->restrictOnDelete();

            $table->index('statement_item_id');
        });
    }
};
