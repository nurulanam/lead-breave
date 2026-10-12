@php
    $checks = $this->checks();
    $dot = ['ok' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-red-500'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">System</x-slot>
        <x-slot name="afterHeader">
            <div class="flex gap-2">
                <x-filament::button tag="a" :href="url(config('horizon.path', 'horizon'))" target="_blank" color="gray" size="xs" outlined>
                    Horizon
                </x-filament::button>
                @if (app()->environment('local') && class_exists(\Laravel\Telescope\Telescope::class))
                    <x-filament::button tag="a" :href="url(config('telescope.path', 'telescope'))" target="_blank" color="gray" size="xs" outlined>
                        Telescope
                    </x-filament::button>
                @endif
            </div>
        </x-slot>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($checks as $check)
                <div>
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $check['label'] }}</dt>
                    <dd class="mt-1 flex items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                        <span class="size-1.5 rounded-full {{ $dot[$check['state']] }}"></span>
                        {{ ucfirst($check['value']) }}
                    </dd>
                    @if ($check['hint'])
                        <dd class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $check['hint'] }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
