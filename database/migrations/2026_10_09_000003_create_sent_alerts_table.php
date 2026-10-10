<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every alert is sent at most once. The unique key is claimed with INSERT ... ON CONFLICT DO NOTHING.
        Schema::create('sent_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->timestampTz('sent_at');
        });
    }
};
