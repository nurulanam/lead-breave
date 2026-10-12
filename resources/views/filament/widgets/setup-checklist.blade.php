<x-filament-widgets::widget class="h-full">
    <x-filament::section class="h-full">
        <x-slot name="heading">Setup</x-slot>
        <x-slot name="description">{{ $done }} of {{ $total }} complete</x-slot>

        <div class="mb-4 h-2 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
            <div class="lb-gradient-bar h-full rounded-full" style="width: {{ round($done / max($total, 1) * 100) }}%"></div>
        </div>

        <ul class="divide-y divide-gray-950/5 dark:divide-white/5">
            @foreach ($items as $item)
                <li class="flex items-center gap-3 py-2.5">
                    @if ($item['done'])
                        <x-filament::icon icon="heroicon-s-check-circle" class="size-5 shrink-0 text-emerald-600 dark:text-emerald-500" />
                    @else
                        <span class="size-5 shrink-0 rounded-full border-[1.5px] border-gray-300 dark:border-gray-600"></span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p @class(['text-sm', 'text-gray-500 dark:text-gray-400' => $item['done'], 'font-medium text-gray-950 dark:text-white' => ! $item['done']])>{{ $item['label'] }}</p>
                        @unless ($item['done'])
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['hint'] }}</p>
                        @endunless
                    </div>

                    @unless ($item['done'])
                        <x-filament::link :href="$item['url']" size="sm" weight="medium">Set up</x-filament::link>
                    @endunless
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
