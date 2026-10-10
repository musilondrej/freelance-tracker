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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            // Filament app authentication (TOTP). Both columns are encrypted with APP_KEY by the model casts.
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            // Alerts. NULL means the alert is off.
            $table->integer('timer_alert_seconds')->nullable()->default(4 * 3600);
            $table->unsignedSmallInteger('recurring_reminder_days')->nullable()->default(7);
            $table->unsignedSmallInteger('deadline_reminder_days')->nullable()->default(2);
            $table->boolean('notify_by_mail')->default(true);
            // Supplier details printed on statements. Empty name = no supplier block.
            $table->string('billing_name')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_registration_number')->nullable();
            $table->string('billing_vat_number')->nullable();
            $table->string('billing_bank_account')->nullable();
            // Preferences.
            $table->unsignedSmallInteger('workday_hours')->default(8);
            $table->char('default_currency', 3)->default('CZK');
            $table->bigInteger('default_hourly_rate')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users
                ADD CONSTRAINT users_timer_alert_check CHECK (timer_alert_seconds > 0),
                ADD CONSTRAINT users_recurring_reminder_check CHECK (recurring_reminder_days BETWEEN 1 AND 90),
                ADD CONSTRAINT users_deadline_reminder_check CHECK (deadline_reminder_days BETWEEN 1 AND 30),
                ADD CONSTRAINT users_workday_hours_check CHECK (workday_hours BETWEEN 1 AND 24),
                ADD CONSTRAINT users_default_currency_check CHECK (default_currency ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT users_default_hourly_rate_check CHECK (default_hourly_rate > 0)
            SQL);

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
};
