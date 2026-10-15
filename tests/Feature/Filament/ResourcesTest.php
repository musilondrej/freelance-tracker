<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Actions\Billing\CreateStatement;
use App\Filament\Pages\TaskBoard;
use App\Filament\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\StatementsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\TasksRelationManager as ClientTasksRelationManager;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Statements\Pages\ViewStatement;
use App\Filament\Resources\Statements\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Widgets\ActiveProjects;
use App\Filament\Widgets\UpcomingDeadlines;
use App\Livewire\TimerIndicator;
use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_renders_every_page_with_many_records_without_lazy_loading(): void
    {
        // Model::shouldBeStrict() throws on lazy loading when a collection has more than one model (N+1 guard).
        $projects = Project::factory()->count(3)->create(['budget' => 100_000]);
        foreach ($projects as $project) {
            $tasks = Task::factory()->count(2)->forProject($project)->create(['due_on' => now()->addDays(3)]);
            TimeEntry::factory()->count(2)->create(['task_id' => $tasks->firstOrFail()]);
            Task::factory()->fixed(10_000)->create(['client_id' => $project->client_id])->markReadyToBill();
        }
        $project = $projects->firstOrFail();
        $client = $project->client;
        $task = $project->tasks()->firstOrFail();
        $task->checklistItems()->create(['title' => 'Item']);
        $statement = app(CreateStatement::class)->handle($client, $client->tasks()->get());
        $lastClient = $projects->last()?->client;
        $this->assertNotNull($lastClient);
        app(CreateStatement::class)->handle($lastClient, $lastClient->tasks()->get());
        TimeEntry::factory()->running()->create();
        $recurring = RecurringTask::factory()->count(2)->create()->firstOrFail();

        foreach ([
            '/', '/task-board', '/unbilled', '/time-entries', '/profile',
            '/reports', '/reports?filters[range]=custom&filters[from]=2026-01-01&filters[to]=2026-12-31', '/reports?filters[range]=custom&filters[from]=nonsense&filters[to]=2026-13-45',
            '/clients', '/clients/create', "/clients/{$client->id}", "/clients/{$client->id}/edit",
            '/projects', '/projects/create', "/projects/{$project->id}", "/projects/{$project->id}/edit",
            '/tasks', '/tasks/create', "/tasks/{$task->id}", "/tasks/{$task->id}/edit",
            '/statements', '/statements/create', "/statements/{$statement->id}", "/statements/{$statement->id}/edit",
            '/recurring-tasks', '/recurring-tasks/create', "/recurring-tasks/{$recurring->id}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // Lazily loaded widgets and relation managers.
        Livewire::test(UpcomingDeadlines::class)->assertOk();
        Livewire::test(ActiveProjects::class)->assertOk();
        Livewire::test(ProjectsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])->assertOk();
        Livewire::test(ClientTasksRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])->assertOk();
        Livewire::test(StatementsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])->assertOk();
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $statement, 'pageClass' => ViewStatement::class])->assertOk();
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class])->assertOk();
    }

    public function test_starts_a_timer_and_asks_before_switching_only_when_another_one_is_running(): void
    {
        $task = Task::factory()->create();

        Livewire::test(ListTasks::class)
            ->mountAction(TestAction::make('startTimer')->table($task))
            ->assertActionNotMounted(TestAction::make('startTimer')->table($task))
            ->assertDispatched('timer-changed');

        $other = Task::factory()->create();

        Livewire::test(ListTasks::class)
            ->mountAction(TestAction::make('startTimer')->table($other))
            ->assertActionMounted(TestAction::make('startTimer')->table($other));

        $this->assertSame($task->id, TimeEntry::query()->running()->sole()->task_id);
    }

    public function test_timer_modal_starts_back_dated_or_logs_a_finished_entry(): void
    {
        $this->freezeSecond();
        $task = Task::factory()->create();
        $start = now()->subMinutes(20)->startOfMinute();
        // The picker works in the display timezone.
        $local = fn (CarbonImmutable $at): string => $at->setTimezone(Period::timezone())->toDateTimeString();

        Livewire::test(TimerIndicator::class)
            ->callAction('start', ['task_id' => $task->id, 'duration' => '0:30', 'started_at' => $local($start->subHours(2))])
            ->callAction('start', ['task_id' => $task->id, 'started_at' => $local($start)]);

        $this->assertSame(1800, TimeEntry::query()->whereNotNull('ended_at')->sole()->duration_seconds);
        $this->assertTrue(TimeEntry::query()->running()->sole()->started_at->equalTo($start));
    }

    public function test_shows_a_blocked_delete_as_a_notification_and_keeps_the_record(): void
    {
        $free = Client::factory()->create();
        $withTemplate = Client::factory()->create();
        RecurringTask::factory()->create(['client_id' => $withTemplate]);

        Livewire::test(ListClients::class)
            ->callAction(TestAction::make('delete')->table($free))
            ->callAction(TestAction::make('delete')->table($withTemplate))
            ->assertNotified(__('It is still in use, so it cannot be deleted. Archive it instead.'));

        $this->assertNull(Client::query()->find($free->id));
        $this->assertNotNull(Client::query()->find($withTemplate->id));
    }

    public function test_offers_creating_related_records_on_the_detail_pages(): void
    {
        $client = Client::factory()->create();

        Livewire::test(ProjectsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertActionVisible(TestAction::make('create')->table());
    }

    public function test_shows_a_domain_rule_violation_as_a_notification_instead_of_an_error_page(): void
    {
        $task = Task::factory()->fixed(100_000)->create();
        $task->markReadyToBill();
        app(CreateStatement::class)->handle($task->client, [$task]);

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->callAction('unmarkReadyToBill')
            ->assertNotified(__('The task is already on a statement.'));

        $this->assertTrue($task->fresh()?->isReadyToBill());
    }

    public function test_suggests_a_client_code_from_the_name_and_finds_tasks_by_key(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm(['name' => 'Acme s.r.o.', 'hourly_rate' => '1200'])
            ->assertSchemaStateSet(['code' => 'ACME'])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::factory()->create(['client_id' => Client::query()->sole(), 'title' => 'Login form']);
        Task::factory()->create(['title' => 'Something else']);

        Livewire::test(ListTasks::class)->searchTable('acme-1')->assertCanSeeTableRecords([$task])->assertCountTableRecords(1);

        $this->assertSame(['ACME-1 Login form'], TaskResource::getGlobalSearchResults('ACME-1')->map->title->all());
    }

    public function test_adds_a_note_on_a_project_and_shows_it_in_the_client_history(): void
    {
        $project = Project::factory()->create(['name' => 'Portal']);

        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callAction(TestAction::make('create')->table(), ['noted_on' => '2026-10-11', 'body' => 'Wants a PDF export'])
            ->assertHasNoActionErrors();

        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $project->client, 'pageClass' => ViewClient::class])
            ->assertSee('Wants a PDF export')
            ->assertSee('Portal');
    }

    public function test_moves_cards_on_a_filtered_kanban_relative_to_the_visible_neighbours(): void
    {
        $acme = Client::factory()->create();
        $other = Client::factory()->create();
        $a = Task::factory()->create(['client_id' => $acme, 'status' => 'todo', 'position' => 0]);
        Task::factory()->create(['client_id' => $other, 'status' => 'todo', 'position' => 1]);
        $b = Task::factory()->create(['client_id' => $acme, 'status' => 'todo', 'position' => 2]);
        $moved = Task::factory()->create(['client_id' => $acme, 'status' => 'backlog']);

        // Dropped at index 1 of the filtered column, so right before $b.
        Livewire::test(TaskBoard::class)
            ->set('clientId', $acme->id)
            ->call('move', (string) $moved->id, 1, 'todo');

        $position = array_flip(Task::query()->where('status', 'todo')->orderBy('position')->pluck('id')->all());
        $this->assertSame($position[$b->id] - 1, $position[$moved->id]);
        $this->assertLessThan($position[$moved->id], $position[$a->id]);
    }
}
