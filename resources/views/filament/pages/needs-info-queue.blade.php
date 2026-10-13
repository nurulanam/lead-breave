@php
    $lead = $this->lead();
    $remaining = $this->remaining();
@endphp

<x-filament-panels::page>
    @if ($lead)
        <div class="grid gap-6 lg:grid-cols-5">
            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">
                    <a href="{{ \App\Filament\Resources\Leads\LeadResource::getUrl('view', ['record' => $lead]) }}" class="hover:underline">{{ $lead->name }}</a>
                </x-slot>
                <x-slot name="description">{{ implode(' · ', array_filter([$lead->category, $lead->city, $lead->region])) }}</x-slot>
                <x-slot name="afterHeader">
                    <x-filament::badge color="warning">{{ number_format($remaining) }} left</x-filament::badge>
                </x-slot>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $lead->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Rating</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $lead->rating ? number_format($lead->rating, 1).' ★ ('.number_format((int) $lead->review_count).')' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Missing</dt>
                        <dd class="flex gap-1.5">
                            @if (blank($lead->website)) <x-filament::badge color="warning" size="sm">Website</x-filament::badge> @endif
                            @if ($lead->emails->isEmpty()) <x-filament::badge color="warning" size="sm">Email</x-filament::badge> @endif
                        </dd>
                    </div>
                </dl>

                <p class="mt-6 mb-2 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Look it up</p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button tag="a" target="_blank" color="gray" size="sm" icon="heroicon-m-magnifying-glass"
                        href="https://www.google.com/search?q={{ urlencode(trim($lead->name.' '.$lead->city)) }}">Google</x-filament::button>
                    @if ($lead->maps_url)
                        <x-filament::button tag="a" target="_blank" color="gray" size="sm" icon="heroicon-m-map-pin" :href="$lead->maps_url">Google Maps</x-filament::button>
                    @endif
                    <x-filament::button tag="a" target="_blank" color="gray" size="sm" icon="heroicon-m-share"
                        href="https://www.facebook.com/search/top?q={{ urlencode(trim($lead->name.' '.$lead->city)) }}">Facebook</x-filament::button>
                    @if ($lead->website)
                        <x-filament::button tag="a" target="_blank" color="gray" size="sm" icon="heroicon-m-globe-alt" :href="$lead->website">Their website</x-filament::button>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section class="lg:col-span-3">
                <x-slot name="heading">What did you find?</x-slot>
                <x-slot name="description">Fill in what you can. Anything entered here is marked as added by hand, so a website check never overwrites it.</x-slot>

                <form wire:submit="save" wire:key="needs-info-{{ $lead->getKey() }}">
                    {{ $this->form }}

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        {{ $this->saveAction }}
                        {{ $this->skipAction }}
                        <span class="flex-1"></span>
                        {{ $this->notAFitAction }}
                    </div>

                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">Ctrl</kbd>/<kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">⌘</kbd> +
                        <kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">Enter</kbd> saves and moves on,
                        <kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">→</kbd> with the same key skips.
                    </p>
                </form>
            </x-filament::section>
        </div>
    @else
        <x-filament::section>
            <div class="flex flex-col items-center gap-3 py-10 text-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <x-filament::icon icon="heroicon-o-check-badge" class="size-7" />
                </span>
                <p class="text-base font-semibold text-gray-950 dark:text-white">All caught up</p>
                <p class="max-w-sm text-sm text-gray-500 dark:text-gray-400">
                    @if ($this->skipped !== [])
                        You skipped {{ count($this->skipped) }} {{ str('lead')->plural(count($this->skipped)) }} this session. Reload the page to see them again.
                    @else
                        Every open lead has a website and an email address.
                    @endif
                </p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
