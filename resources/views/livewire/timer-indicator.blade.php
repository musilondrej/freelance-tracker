@use('App\Filament\Resources\Tasks\TaskResource')

@php
    $entry = $this->running;
@endphp

<div wire:poll.60s @class(['flex items-center', 'w-full' => $large])>
    @if ($entry)
        <div
            x-data="{
                start: {{ $entry->started_at->getTimestampMs() }},
                offset: {{ now()->getTimestampMs() }} - Date.now(),
                now: Date.now(),
                timer: null,
                init() { this.timer = setInterval(() => this.now = Date.now(), 1000) },
                destroy() { clearInterval(this.timer) },
                get elapsed() {
                    const s = Math.max(0, Math.floor((this.now + this.offset - this.start) / 1000))
                    const pad = (n) => String(n).padStart(2, '0')
                    return Math.floor(s / 3600) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60)
                },
            }"
            @class([
                'flex min-w-0 items-center gap-2 rounded-lg',
                // Topbar: neutral like the search field, only the dot says "running".
                'h-9 bg-white px-3 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20' => ! $large,
                'w-full bg-success-50 px-4 py-3 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:ring-success-400/20' => $large,
            ])
        >
            <span class="relative flex h-2 w-2 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
            </span>

            <a href="{{ TaskResource::getUrl('view', ['record' => $entry->task_id]) }}" class="min-w-0" title="{{ $entry->task->title }}">
                <span @class(['block truncate', 'max-w-48 text-sm text-gray-700 hidden md:block dark:text-gray-200' => ! $large, 'text-base font-medium text-success-800 dark:text-success-300' => $large])>
                    {{ $entry->task->title }}
                </span>
                @if ($large)
                    <span class="block truncate text-sm text-success-700/80 dark:text-success-300/70">
                        {{ collect([$entry->task->contextLabel(), $entry->note])->filter()->implode(' · ') }}
                    </span>
                @endif
            </a>

            <span x-text="elapsed" @class(['ms-auto tabular-nums', 'text-sm font-medium text-gray-950 dark:text-white' => ! $large, 'font-mono text-2xl font-semibold text-success-800 dark:text-success-300' => $large])></span>

            <x-filament::icon-button
                icon="heroicon-m-stop"
                :color="$large ? 'danger' : 'gray'"
                @class(['hover:!text-danger-600 dark:hover:!text-danger-400' => ! $large])
                :size="$large ? 'lg' : 'sm'"
                wire:click="stop"
                :label="__('Stop timer')"
                :tooltip="__('Stop timer')"
            />
        </div>
    @else
        @if ($large)
            <div class="flex w-full items-center gap-4 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-clock" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('No timer is running') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Start it here or on any task.') }}</p>
                </div>
                <div class="ms-auto shrink-0">
                    {{ $this->startAction }}
                </div>
            </div>
        @else
            {{ $this->startAction->iconButton()->tooltip(__('Start timer')) }}
        @endif
    @endif

    <x-filament-actions::modals />
</div>
