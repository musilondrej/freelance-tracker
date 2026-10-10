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
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Task key prefix, like ACME in ACME-12.
            $table->string('code', 10)->unique();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('registration_number', 20)->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->default('CZ');
            $table->text('notes')->nullable();
            $table->bigInteger('hourly_rate');
            $table->char('currency', 3);
            // A counter, so the number of a deleted task is never handed out again.
            $table->integer('last_task_number')->default(0);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            // Target of statements (client_id, currency) so a statement always matches its client's currency.
            $table->unique(['id', 'currency']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE clients
                ADD CONSTRAINT clients_hourly_rate_check CHECK (hourly_rate > 0),
                ADD CONSTRAINT clients_currency_check CHECK (currency ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT clients_code_format CHECK (code ~ '^[A-Z][A-Z0-9]{1,9}$'),
                ADD CONSTRAINT clients_last_task_number_check CHECK (last_task_number >= 0)
            SQL);
    }
};
