<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_note_belongs_to_the_tasks_project_and_client(): void
    {
        $task = Task::factory()->forProject(Project::factory()->create())->create();

        $note = $task->notes()->create(['noted_on' => '2026-10-11', 'body' => 'Wants a PDF export']);

        $this->assertSame([$task->client_id, $task->project_id], [$note->client_id, $note->project_id]);
        $this->assertSame([$note->id], $task->client->notes()->pluck('id')->all());
        $this->assertSame([$note->id], $task->project?->notes()->pluck('id')->all());
    }

    public function test_deleting_a_project_keeps_its_notes_on_the_client(): void
    {
        $project = Project::factory()->create();
        $note = $project->notes()->create(['client_id' => $project->client_id, 'noted_on' => '2026-10-11', 'body' => 'Kick-off']);

        $project->delete();

        $this->assertNull($note->fresh()?->project_id);
        $this->assertSame($project->client_id, $note->fresh()?->client_id);
    }

    public function test_the_subject_names_the_task_or_project(): void
    {
        $task = Task::factory()->forProject(Project::factory()->create(['name' => 'Portal']))->create(['title' => 'Login']);

        $this->assertSame($task->key().' Login', $task->notes()->create(['noted_on' => '2026-10-11', 'body' => 'a'])->subjectLabel());
        $this->assertSame('Portal', Note::query()->create([
            'client_id' => $task->client_id, 'project_id' => $task->project_id, 'noted_on' => '2026-10-11', 'body' => 'b',
        ])->subjectLabel());
    }
}
