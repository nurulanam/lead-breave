@php
    $stageColors = ['#22d3ee', '#38bdf8', '#818cf8', '#8b5cf6', '#c084fc', '#e879f9', '#34d399'];
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section class="h-full">
        <x-slot name="heading">Pipeline</x-slot>
        <x-slot name="description">Leads by stage, from search to booked call</x-slot>
        <x-slot name="afterHeader">
            <span class="rounded-full bg-gray-950/5 px-2.5 py-1 text-xs font-medium text-gray-600 tabular-nums dark:bg-white/10 dark:text-gray-300">
                {{ number_format($total) }} total
            </span>
        </x-slot>

        <ol class="space-y-1">
            @foreach ($stages as $stage)
                <li class="group grid grid-cols-[minmax(0,10rem)_1fr_auto_auto] items-center gap-x-4 rounded-xl px-2 py-2 transition hover:bg-gray-950/[0.03] sm:grid-cols-[minmax(0,12rem)_1fr_3rem_3.5rem] dark:hover:bg-white/[0.04]">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg text-[11px] font-semibold text-white shadow-sm" style="background: {{ $stageColors[$loop->index] }}">
                            {{ $loop->iteration }}
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $stage['label'] }}</div>
                            <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $stage['description'] }}</div>
                        </div>
                    </div>

                    <div class="h-2 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
                        <div class="lb-gradient-bar h-full rounded-full" style="width: {{ $stage['percent'] ?? 0 }}%"></div>
                    </div>

                    <div class="text-end text-sm font-semibold text-gray-950 tabular-nums dark:text-white">{{ number_format($stage['count']) }}</div>
                    <div class="text-end text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $stage['percent'] === null ? '—' : $stage['percent'].'%' }}</div>
                </li>
            @endforeach
        </ol>

        @if ($total === 0)
            <div class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-gray-300/80 px-4 py-3 dark:border-white/15">
                <x-filament::icon icon="heroicon-o-map" class="size-5 shrink-0 text-primary-500" />
                <p class="text-sm text-gray-600 dark:text-gray-400">No leads yet. They will appear here once lead search is available.</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
