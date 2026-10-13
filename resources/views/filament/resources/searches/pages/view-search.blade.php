<x-filament-panels::page>
    @if ($this->isInProgress())
        <div wire:poll.3s="refreshProgress" class="hidden"></div>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
