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
        // Dated history of a client: what was asked, agreed or decided. Optionally about a project or task.
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->index();
            $table->foreignId('task_id')->nullable()->index();
            $table->date('noted_on');
            $table->text('body');
            $table->timestampsTz();

            $table->index(['client_id', 'noted_on']);
        });

        // The project and task must belong to the note's client. Deleting them keeps the note on the client.
        DB::statement(<<<'SQL'
            ALTER TABLE notes
                ADD CONSTRAINT notes_project_client_foreign FOREIGN KEY (project_id, client_id)
                    REFERENCES projects (id, client_id) ON DELETE SET NULL (project_id),
                ADD CONSTRAINT notes_task_client_foreign FOREIGN KEY (task_id, client_id)
                    REFERENCES tasks (id, client_id) ON DELETE SET NULL (task_id)
            SQL);
    }
};
