{{-- Panel logo: a clock mark in the primary colour, plus the name. Inherits light and dark mode. --}}
<div class="flex items-center gap-2.5">
    <svg viewBox="0 0 32 32" class="size-7 shrink-0 text-primary-600 dark:text-primary-500" aria-hidden="true">
        <rect width="32" height="32" rx="8" fill="currentColor" />
        <circle cx="16" cy="16" r="9" fill="none" stroke="white" stroke-width="2.5" />
        <path d="M16 11.5V16l3.5 2.5" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white">{{ filament()->getBrandName() }}</span>
</div>
