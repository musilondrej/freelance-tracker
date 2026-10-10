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
        // ČNB daily fixing. Only for overview totals in CZK; stored amounts are never converted.
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('rate_on');
            $table->char('currency', 3);
            // CZK for `amount` units of the currency, in thousandths: EUR 24,365 = amount 1, rate 24365.
            $table->unsignedInteger('amount');
            $table->bigInteger('rate');
            $table->timestampsTz();

            $table->unique(['currency', 'rate_on']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE exchange_rates
                ADD CONSTRAINT exchange_rates_currency_check CHECK (currency ~ '^[A-Z]{3}$' AND currency <> 'CZK'),
                ADD CONSTRAINT exchange_rates_amount_check CHECK (amount > 0),
                ADD CONSTRAINT exchange_rates_rate_check CHECK (rate > 0)
            SQL);
    }
};
