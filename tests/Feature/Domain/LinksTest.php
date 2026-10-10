<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Filament\RelationManagers\LinksRelationManager;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_link_shows_its_title_or_domain(): void
    {
        $task = Task::factory()->create();

        $this->assertSame('Contract', $task->links()->create(['title' => 'Contract', 'url' => 'https://drive.example.com/d/1'])->label());
        $this->assertSame('staging.example.com', $task->links()->create(['url' => 'https://www.staging.example.com/login'])->label());
    }

    public function test_adds_a_link_to_a_task_and_lists_it_on_the_client(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::factory()->forProject(Project::factory()->create())->create(['title' => 'Login']);

        $links = fn () => Livewire::test(LinksRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class]);
        $links()->callAction(TestAction::make('create')->table(), ['url' => 'javascript:alert(1)'])->assertHasActionErrors(['url']);
        $links()->callAction(TestAction::make('create')->table(), ['url' => 'https://staging.example.com', 'title' => 'Staging'])
            ->assertHasNoActionErrors();

        $link = $task->links()->sole();
        $this->assertSame([$task->client_id, $task->project_id], [$link->client_id, $link->project_id]);

        Livewire::test(LinksRelationManager::class, ['ownerRecord' => $task->client, 'pageClass' => ViewClient::class])
            ->assertSee('Staging')
            ->assertSee($task->key().' Login');
    }
}
