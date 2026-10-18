@use('App\Filament\Resources\Tasks\TaskResource')
@use('Illuminate\Support\Js')

@php
    $entry = $this->running;
@endphp

<div
    wire:poll.60s
    {{-- Same spacing as the other topbar items. --}}
    @class(['flex items-center', 'w-full' => $large, 'gap-x-4' => ! $large])
    @unless ($large)
        {{-- Other tabs refresh at once when the timer starts or stops here, and the other way round. --}}
        x-data="{
            channel: new BroadcastChannel('timer'),
            relaying: false,
            init() {
                this.channel.onmessage = () => {
                    this.relaying = true
                    Livewire.dispatch('timer-changed')
                    this.relaying = false
                }
            },
            destroy() { this.channel.close() },
        }"
        x-on:timer-changed.window="relaying || channel.postMessage(null)"
    @endunless
>
    @if ($entry)
        <div
            wire:key="running-{{ $entry->id }}"
            x-data="{
                start: {{ $entry->started_at->getTimestampMs() }},
                offset: {{ now()->getTimestampMs() }} - Date.now(),
                now: Date.now(),
                timer: null,
                prefix: '',
                init() {
                    this.tick()
                    this.timer = setInterval(() => this.tick(), 1000)
                },
                destroy() {
                    clearInterval(this.timer)
                    this.retitle('')
                },
                tick() {
                    this.now = Date.now()
                    @unless ($large)
                        this.retitle(this.elapsed + ' · ' + @js($entry->task->title) + ' · ')
                    @endunless
                },
                {{-- The tab shows the running time; page navigation replaces the title, so strip only our own prefix. --}}
                retitle(prefix) {
                    const page = document.title.startsWith(this.prefix) ? document.title.slice(this.prefix.length) : document.title
                    this.prefix = prefix
                    document.title = prefix + page
                },
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

    @if (! $large && $this->recentTasks->isNotEmpty())
        <x-filament::dropdown placement="bottom-end" width="sm">
            <x-slot name="trigger">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-path"
                    color="gray"
                    :label="__('Restart a recent task')"
                    :tooltip="__('Restart a recent task')"
                />
            </x-slot>

            <x-filament::dropdown.header>{{ __('Restart a recent task') }}</x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($this->recentTasks as $task)
                    <x-filament::dropdown.list.item
                        wire:key="recent-{{ $task->id }}"
                        wire:click="mountAction('restart', {{ Js::from(['task' => $task->id]) }})"
                        x-on:click="close"
                        icon="heroicon-m-play"
                    >
                        <span class="block truncate font-medium">{{ $task->title }}</span>
                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-mono">{{ $task->key() }}</span> · {{ $task->contextLabel() }}
                        </span>
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif

    <x-filament-actions::modals />
</div>
