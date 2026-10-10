<x-filament::input.wrapper @class([$class ?? null])>
    <x-filament::input.select wire:model.live="clientId" aria-label="{{ __('Client') }}">
        <option value="">{{ __('All clients') }}</option>
        @foreach ($this->clients as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>

<x-filament::input.wrapper @class([$class ?? null])>
    <x-filament::input.select wire:model.live="projectId" aria-label="{{ __('Project') }}">
        <option value="">{{ __('All projects') }}</option>
        @foreach ($this->projects as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>
