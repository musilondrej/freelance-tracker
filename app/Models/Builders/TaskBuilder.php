<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\PricingType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of Task
 *
 * @extends ArchivableBuilder<TModel>
 */
final class TaskBuilder extends ArchivableBuilder
{
    /** The task with the given key, like ACME-12, in any case. Nothing when $key is not a key. */
    public function withKey(string $key): static
    {
        $parsed = Task::parseKey($key);

        if ($parsed === null) {
            return $this->whereRaw('false');
        }

        return $this->where('tasks.number', $parsed['number'])
            ->whereHas('client', fn (ArchivableBuilder $query) => $query->where('code', $parsed['code']));
    }

    public function open(): static
    {
        return $this->where('tasks.status', '!=', TaskStatus::Done);
    }

    /** Leaves out ongoing tasks, which serve only for time tracking. */
    public function withoutOngoing(): static
    {
        return $this->where('tasks.is_ongoing', false);
    }

    /** Fixed-price tasks marked as ready to bill. Their price counts as work value from then on. */
    public function readyFixed(): static
    {
        return $this->where('tasks.pricing_type', PricingType::Fixed)->whereNotNull('tasks.ready_to_bill_at');
    }

    /** Fixed prices that became work value within the period. */
    public function readyFixedIn(Period $period): static
    {
        return $this->readyFixed()
            ->where('tasks.ready_to_bill_at', '>=', $period->start)
            ->where('tasks.ready_to_bill_at', '<', $period->end);
    }

    /** Fixed-price tasks not ready to bill yet. Their price is planned, not consumed. */
    public function pendingFixed(): static
    {
        return $this->where('tasks.pricing_type', PricingType::Fixed)->whereNull('tasks.ready_to_bill_at');
    }

    /** Ready fixed-price tasks that are not on any active statement. Same rule as Task::isFixedPriceBilled(). */
    public function unbilledFixed(): static
    {
        return $this->readyFixed()->whereDoesntHave('statementItems', fn (StatementItemBuilder $items) => $items->fixed()->onActiveStatement());
    }

    /** Adds `tracked_seconds` and `hourly_value` for Task::trackedSeconds() and Task::value() in lists. */
    public function withTracking(): static
    {
        return $this->withSum('timeEntries as tracked_seconds', 'duration_seconds')
            ->withSum('timeEntries as hourly_value', 'amount');
    }

    /** The most recently tracked tasks first, untracked ones last. */
    public function latestTrackedFirst(): static
    {
        return $this->withMax('timeEntries as last_tracked_at', 'started_at')
            ->orderByRaw('last_tracked_at DESC NULLS LAST');
    }

    /** Adds `checklist_items_count` and `checklist_done_count`. */
    public function withChecklistProgress(): static
    {
        return $this->withCount(['checklistItems', 'checklistItems as checklist_done_count' => fn (Builder $items) => $items->where('is_done', true)]);
    }

    /** What the kanban shows: open tasks that are not ongoing, plus done ones for a few days so a finished card does not vanish at once. */
    public function onBoard(int $doneVisibleDays): static
    {
        return $this->withoutOngoing()
            ->where(fn (self $query) => $query
                ->open()
                ->orWhere('tasks.completed_at', '>=', now()->subDays($doneVisibleDays)));
    }

    /** Open, with a deadline up to the given number of days ahead, overdue ones included. */
    public function dueBy(int $days): static
    {
        return $this->open()->where('tasks.due_on', '<=', Period::localToday()->addDays($days)->toDateString());
    }

    /** Open, with a deadline from today to the given number of days ahead. */
    public function dueWithin(int $days): static
    {
        return $this->dueBy($days)->where('tasks.due_on', '>=', Period::localToday()->toDateString());
    }

    /** Open and past the deadline. Same rule as Task::isOverdue(). */
    public function overdue(): static
    {
        return $this->open()->where('tasks.due_on', '<', Period::localToday()->toDateString());
    }
}
