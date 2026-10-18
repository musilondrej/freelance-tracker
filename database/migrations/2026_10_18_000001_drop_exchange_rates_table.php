<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Overviews keep each currency on its own line, so the ČNB rates are no longer needed.
        Schema::drop('exchange_rates');
    }
};
