<x-filament-panels::page>
    <livewire:website-check-progress :lead-id="$this->record->getKey()" :key="'website-check-progress-'.$this->record->getKey()" />

    {{ $this->content }}
</x-filament-panels::page>
