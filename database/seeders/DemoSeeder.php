<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Billing\CreateStatement;
use App\Actions\Billing\SendStatement;
use App\Actions\Recurring\GenerateRecurringTasks;
use App\Actions\TimeTracking\StartTimer;
use App\Enums\BillingTiming;
use App\Enums\Currency;
use App\Enums\PricingType;
use App\Enums\ProjectStatus;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional demo data for local development: `php artisan db:seed --class=DemoSeeder`.
 * Creates the local-only account demo@example.com / password. Refuses to run outside the local environment.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('DemoSeeder only runs in the local environment.');
        }

        User::query()->firstOrCreate(['email' => 'demo@example.com'], ['name' => 'Demo', 'password' => 'password']);

        $acme = Client::factory()->create([
            'name' => 'Acme s.r.o.', 'code' => 'ACME', 'company_name' => 'Acme s.r.o.', 'email' => 'info@acme.example',
            'registration_number' => '12345678', 'vat_number' => 'CZ12345678',
            'street' => 'Vzorová 1', 'city' => 'Praha', 'postal_code' => '110 00', 'hourly_rate' => 120_000,
        ]);
        $startup = Client::factory()->create([
            'name' => 'Startup GmbH', 'code' => 'STUP', 'email' => 'hello@startup.example', 'currency' => Currency::EUR,
            'hourly_rate' => 6_000, 'city' => 'Berlin', 'country_code' => 'DE',
        ]);
        $studio = Client::factory()->create(['name' => 'Studio Nova', 'code' => 'NOVA', 'hourly_rate' => 100_000]);

        $portal = Project::factory()->create([
            'client_id' => $acme->id, 'name' => 'Klientský portál', 'budget' => 10_000_000,
            'estimated_seconds' => 90 * 3600, 'hourly_rate' => 140_000, 'due_on' => now()->addMonth(),
        ]);
        $api = Project::factory()->create(['client_id' => $acme->id, 'name' => 'API Development', 'budget' => 3_000_000]);
        $saas = Project::factory()->create(['client_id' => $startup->id, 'name' => 'SaaS Platform', 'budget' => 1_500_000]);
        Project::factory()->create(['client_id' => $studio->id, 'name' => 'Web redesign', 'status' => ProjectStatus::Paused]);

        $auth = $this->task($portal, 'Implementace autentizace', TaskStatus::InProgress, TaskPriority::High, hours: [3, 2.5, 4, 1.5]);
        $auth->checklistItems()->createMany([
            ['title' => 'Login endpoint', 'is_done' => true, 'position' => 0],
            ['title' => 'Refresh token', 'is_done' => true, 'position' => 1],
            ['title' => 'Password reset', 'position' => 2],
            ['title' => 'Integrační testy', 'position' => 3],
        ]);
        $this->task($portal, 'Dashboard klienta', TaskStatus::Todo, TaskPriority::Normal, due: now()->addDays(5));
        $this->task($portal, 'Export faktur do PDF', TaskStatus::Review, TaskPriority::Normal, hours: [2, 3]);
        $this->task($portal, 'Notifikace e-mailem', TaskStatus::Backlog, TaskPriority::Low);
        $webhook = $this->task($api, 'Implementace webhooku', TaskStatus::Done, TaskPriority::Normal, hours: [3, 4], fixed: 500_000);
        $webhook->markReadyToBill();
        $this->task($api, 'Rate limiting', TaskStatus::Waiting, TaskPriority::High, hours: [1], due: now()->subDay());
        $this->task($api, 'Interní refactoring', TaskStatus::InProgress, TaskPriority::Normal, hours: [2, 1], nonBillable: true);
        $this->task($saas, 'Refactoring API', TaskStatus::InProgress, TaskPriority::Urgent, hours: [4, 3, 2.5]);
        $this->task($saas, 'Onboarding flow', TaskStatus::Todo, TaskPriority::Normal, fixed: 80_000);
        $form = Task::factory()->fixed(150_000)->create(['client_id' => $acme->id, 'title' => 'Oprava kontaktního formuláře', 'status' => TaskStatus::Todo]);
        $this->entries($form, [1]);

        // Last month's work on the API project: on a sent statement.
        $old = $this->task($api, 'Návrh API kontraktu', TaskStatus::Done, TaskPriority::Normal);
        $this->entries($old, [3, 2, 4], CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->addDays(3));
        app(SendStatement::class)->handle(app(CreateStatement::class)->handle($acme, [$old]), now()->subDays(20)->toImmutable());

        // Fixed webhook billed in a draft.
        app(CreateStatement::class)->handle($acme, [$webhook]);

        // Recurring work: monthly maintenance billed after the month, yearly hosting billed in advance.
        RecurringTask::factory()->create([
            'client_id' => $acme->id, 'project_id' => $portal->id, 'title' => 'Správa portálu',
            'fixed_price' => 300_000, 'starts_on' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
        ]);
        RecurringTask::factory()->create([
            'client_id' => $startup->id, 'title' => 'Hosting', 'frequency' => RecurrenceFrequency::Yearly,
            'fixed_price' => 24_000, 'billing_timing' => BillingTiming::InAdvance, 'starts_on' => now()->startOfYear()->toDateString(),
        ]);
        app(GenerateRecurringTasks::class)->handle();

        app(StartTimer::class)->handle($auth, note: 'Password reset');
    }

    /**
     * @param  list<float>  $hours
     */
    private function task(
        Project $project,
        string $title,
        TaskStatus $status,
        TaskPriority $priority,
        array $hours = [],
        ?int $fixed = null,
        bool $nonBillable = false,
        mixed $due = null,
    ): Task {
        $task = Task::factory()->forProject($project)->create([
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'pricing_type' => $fixed !== null ? PricingType::Fixed : ($nonBillable ? PricingType::NonBillable : PricingType::Hourly),
            'fixed_price' => $fixed,
            'due_on' => $due,
            'estimated_seconds' => $hours === [] ? null : (int) (array_sum($hours) * 3600 * 1.2),
        ]);

        $this->entries($task, $hours);

        return $task;
    }

    /**
     * @param  list<float|int>  $hours
     */
    private function entries(Task $task, array $hours, ?CarbonImmutable $from = null): void
    {
        $day = $from ?? CarbonImmutable::now()->subDays(count($hours) + 1);

        foreach ($hours as $i => $h) {
            $start = $day->addDays($i)->setTime(9 + $i % 3, 0);
            TimeEntry::query()->create([
                'task_id' => $task->id,
                'started_at' => $start,
                'ended_at' => $start->addSeconds((int) ($h * 3600)),
                'note' => fake()->optional()->sentence(4),
            ]);
        }
    }
}
