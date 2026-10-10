@use('App\Support\Period')
@use('App\Enums\TaskPriority')
@use('App\Enums\TaskStatus')
@use('App\Enums\PricingType')
@use('App\Filament\Resources\Tasks\TaskResource')
@use('App\Support\Duration')

<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        @include('filament.pages.partials.client-project-filters', ['class' => 'w-full sm:w-64'])
    </div>

    <div class="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:px-0">
        @foreach (TaskStatus::cases() as $status)
            @php($tasks = $this->columns[$status->value])

            <section class="flex w-72 shrink-0 snap-start flex-col rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" wire:key="column-{{ $status->value }}">
                <header class="flex items-center gap-2 px-3 pt-3 pb-2">
                    <x-filament::icon :icon="$status->getIcon()" @class(['h-4 w-4', 'text-gray-400' => $status === TaskStatus::Backlog]) style="color: var(--{{ $status->getColor() }}-500)" />
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $status->getLabel() }}</h2>
                    <span class="ms-auto rounded-md bg-gray-200/70 px-1.5 text-xs font-medium tabular-nums text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $tasks->count() }}</span>
                </header>

                <div
                    class="flex min-h-24 flex-1 flex-col gap-2 px-2 pb-2"
                    wire:sort="move"
                    wire:sort:group="tasks"
                    wire:sort:group-id="{{ $status->value }}"
                >
                    @foreach ($tasks as $task)
                        <article
                            wire:key="task-{{ $task->id }}"
                            wire:sort:item="{{ $task->id }}"
                            class="group cursor-grab rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 transition hover:ring-primary-500/40 active:cursor-grabbing dark:bg-gray-900 dark:ring-white/10"
                        >
                            <div class="flex items-start gap-2">
                                <a href="{{ TaskResource::getUrl('view', ['record' => $task]) }}" class="flex-1 text-sm font-medium leading-snug text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400" wire:sort:ignore>
                                    {{ $task->title }}
                                </a>

                                <div wire:sort:ignore class="opacity-60 transition group-hover:opacity-100">
                                    {{ ($this->startTimerAction)(['task' => $task->id]) }}
                                </div>
                            </div>

                            <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-mono">{{ $task->key() }}</span> · {{ $task->contextLabel() }}
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                @if ($task->priority->standsOut())
                                    <x-filament::badge :color="$task->priority->getColor()" size="sm" :icon="$task->priority->getIcon()">
                                        {{ $task->priority->getLabel() }}
                                    </x-filament::badge>
                                @endif

                                @if ($task->due_on)
                                    <span @class(['inline-flex items-center gap-1', 'font-medium text-danger-600 dark:text-danger-400' => $task->isOverdue()])>
                                        <x-filament::icon icon="heroicon-m-calendar" class="h-3.5 w-3.5" />
                                        {{ $task->due_on->format(Period::SHORT_DATE_FORMAT) }}
                                    </span>
                                @endif

                                @if ($task->tracked_seconds)
                                    <span class="inline-flex items-center gap-1 tabular-nums">
                                        <x-filament::icon icon="heroicon-m-clock" class="h-3.5 w-3.5" />
                                        {{ Duration::format((int) $task->tracked_seconds) }}
                                    </span>
                                @endif

                                @if ($task->checklist_items_count)
                                    <span @class(['inline-flex items-center gap-1 tabular-nums', 'text-success-600 dark:text-success-400' => $task->checklist_done_count === $task->checklist_items_count])>
                                        <x-filament::icon icon="heroicon-m-check-circle" class="h-3.5 w-3.5" />
                                        {{ $task->checklistProgress() }}
                                    </span>
                                @endif

                                @if ($task->pricing_type !== PricingType::Hourly)
                                    <span class="inline-flex items-center gap-1" title="{{ $task->pricing_type->getLabel() }}">
                                        <x-filament::icon :icon="$task->pricing_type->getIcon()" class="h-3.5 w-3.5" />
                                    </span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <a href="{{ TaskResource::getUrl('create', array_filter(['status' => $status->value, 'client' => $clientId, 'project' => $projectId])) }}"
                   class="mx-2 mb-2 flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-200/60 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                    {{ __('Add task') }}
                </a>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
