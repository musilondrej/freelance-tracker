<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\BillingTiming;
use App\Enums\PricingType;
use App\Enums\RecurrenceFrequency;
use App\Filament\Resources\RecurringTasks\Pages\CreateRecurringTask;
use App\Filament\Resources\RecurringTasks\Pages\EditRecurringTask;
use App\Filament\Resources\RecurringTasks\Pages\ListRecurringTasks;
use App\Filament\Resources\RecurringTasks\RelationManagers\GeneratedTasksRelationManager;
use App\Models\Client;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\User;
use App\Support\Period;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class RecurringUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_creates_a_recurring_task_and_generates_the_current_period_right_away(): void
    {
        $client = Client::factory()->create();
        $today = Period::localToday();

        Livewire::test(CreateRecurringTask::class)
            ->fillForm([
                'title' => 'Hosting',
                'client_id' => $client->id,
                'frequency' => RecurrenceFrequency::Yearly,
                'interval' => 1,
                'starts_on' => $today->startOfYear()->toDateString(),
                'pricing_type' => PricingType::Fixed,
                'fixed_price' => '3 600',
                'billing_timing' => BillingTiming::InAdvance,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $recurring = RecurringTask::query()->sole();
        $task = $recurring->tasks()->sole();
        $this->assertSame(360_000, $recurring->fixed_price);
        $this->assertSame('Hosting – '.$today->year, $task->title);
        $this->assertNotNull($task->ready_to_bill_at);
    }

    public function test_generates_now_and_lists_the_generated_tasks(): void
    {
        $recurring = RecurringTask::factory()->create();

        Livewire::test(ListRecurringTasks::class)->callAction('generate')->assertNotified();

        $this->assertSame(1, $recurring->tasks()->count());
        Livewire::test(GeneratedTasksRelationManager::class, ['ownerRecord' => $recurring, 'pageClass' => EditRecurringTask::class])
            ->assertCanSeeTableRecords(Task::all());
    }
}
