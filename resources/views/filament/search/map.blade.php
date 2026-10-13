{{--
    Search area and cells on a map. Pass $data from SearchMapData. The map keeps
    itself in sync through the "search-map-updated" browser event, so Livewire
    must not morph it.
--}}
@php
    $legend = [
        'pending' => ['label' => 'Queued', 'class' => 'bg-slate-600'],
        'running' => ['label' => 'Searching', 'class' => 'bg-violet-500'],
        'done' => ['label' => 'Done', 'class' => 'bg-emerald-500'],
        'failed' => ['label' => 'Failed', 'class' => 'bg-red-500'],
    ];
@endphp

<div class="lb-map">
    @if ($data)
        <div
            wire:ignore
            wire:key="{{ $mapKey ?? 'search-map-'.md5(json_encode($data['area'] ?? null)).'-'.count($data['tiles'] ?? []) }}"
            x-data="searchMap(@js($data))"
            x-on:search-map-updated.window="render($event.detail.data)"
        >
            <div x-ref="canvas" class="{{ $height ?? 'h-80' }} w-full overflow-hidden rounded-xl ring-1 ring-gray-950/10 dark:ring-white/10"></div>
        </div>

        @if ($showLegend ?? true)
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                @foreach ($legend as $item)
                    <li class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm {{ $item['class'] }}"></span>
                        {{ $item['label'] }}
                    </li>
                @endforeach
                <li class="flex items-center gap-1.5">
                    <span class="h-0 w-4 border-t-2 border-dashed border-violet-600"></span>
                    Search area
                </li>
            </ul>
        @endif
    @else
        <div class="flex {{ $height ?? 'h-80' }} flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 text-center dark:border-white/15">
            <x-filament::icon icon="heroicon-o-map" class="size-8 text-gray-400" />
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $emptyHeading ?? 'No area yet' }}</p>
            <p class="max-w-xs text-xs text-gray-500 dark:text-gray-400">{{ $emptyDescription ?? '' }}</p>
        </div>
    @endif
</div>
