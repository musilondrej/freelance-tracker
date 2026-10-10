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
        // Web links of a client, optionally about a project or task. Same ownership rules as notes.
        Schema::create('links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->index();
            $table->foreignId('task_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('url', 2048);
            $table->timestampsTz();

            $table->index('client_id');
        });

        // Only http(s): a javascript: or data: URL would run script when clicked.
        DB::statement(<<<'SQL'
            ALTER TABLE links
                ADD CONSTRAINT links_project_client_foreign FOREIGN KEY (project_id, client_id)
                    REFERENCES projects (id, client_id) ON DELETE SET NULL (project_id),
                ADD CONSTRAINT links_task_client_foreign FOREIGN KEY (task_id, client_id)
                    REFERENCES tasks (id, client_id) ON DELETE SET NULL (task_id),
                ADD CONSTRAINT links_url_check CHECK (url ~* '^https?://[^\s]+$')
            SQL);
    }
};
