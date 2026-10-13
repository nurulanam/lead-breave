{{-- @var \App\Services\Search\CoverageEstimate|null $estimate --}}
<div class="space-y-4">
    @include('filament.search.map', [
        'data' => $map,
        'height' => 'h-80',
        'mapKey' => 'search-preview-map',
        'showLegend' => false,
    ])

    <div class="flex min-h-5 items-center gap-2 text-sm">
        <span wire:loading.flex wire:target="data.location" class="items-center gap-2 text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="size-4" />
            Finding the area…
        </span>

        <span wire:loading.remove wire:target="data.location" class="flex items-center gap-2">
            @if ($error)
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-4 shrink-0 text-amber-500" />
                <span class="text-amber-700 dark:text-amber-300">{{ $error }}</span>
            @elseif ($label)
                <x-filament::icon icon="heroicon-m-check-circle" class="size-4 shrink-0 text-emerald-500" />
                <span class="text-gray-700 dark:text-gray-200">{{ $label }}</span>
            @else
                <x-filament::icon icon="heroicon-m-map-pin" class="size-4 shrink-0 text-gray-400" />
                <span class="text-gray-500 dark:text-gray-400">Type a ZIP code or city to see the area and what it will cost.</span>
            @endif
        </span>
    </div>

    @if ($estimate)
        <dl class="grid grid-cols-3 divide-x divide-gray-950/5 rounded-xl bg-gray-950/[0.03] text-center dark:divide-white/10 dark:bg-white/5">
            <div class="px-3 py-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Cells</dt>
                <dd class="mt-0.5 text-lg font-semibold text-gray-950 tabular-nums dark:text-white">{{ count($estimate->tiles) }}</dd>
            </div>
            <div class="px-3 py-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Google requests</dt>
                <dd class="mt-0.5 text-lg font-semibold text-gray-950 tabular-nums dark:text-white">{{ $estimate->minRequests }}–{{ $estimate->maxRequests }}</dd>
            </div>
            <div class="px-3 py-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Estimated cost</dt>
                <dd class="mt-0.5 text-lg font-semibold text-gray-950 tabular-nums dark:text-white">${{ number_format($estimate->minCost, 2) }}–{{ number_format($estimate->maxCost, 2) }}</dd>
            </div>
        </dl>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Each cell costs one to three requests, more if it is crowded enough to split.
            {{ number_format($usedThisMonth) }} of {{ number_format($monthlyCap) }} requests used this month.
        </p>
    @endif
</div>
