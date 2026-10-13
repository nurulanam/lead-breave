{{-- Quick while a check runs; slow otherwise, so checks started elsewhere still appear. --}}
<div @if ($active) wire:poll.1500ms="tick" @else wire:poll.5s="tick" @endif>
    @if ($visible)
        @php
            $state = $progress['state'];
            $done = $state === \App\Services\Website\CheckProgress::DONE;
            $failed = $state === \App\Services\Website\CheckProgress::FAILED;
        @endphp

        <div @class([
            'flex items-center gap-4 rounded-2xl px-5 py-4 ring-1 backdrop-blur',
            'bg-white/70 ring-primary-500/20 dark:bg-white/5 dark:ring-primary-400/20' => $active,
            'bg-emerald-50/80 ring-emerald-600/15 dark:bg-emerald-500/10 dark:ring-emerald-400/20' => $done,
            'bg-red-50/80 ring-red-600/15 dark:bg-red-500/10 dark:ring-red-400/20' => $failed,
        ])>
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/80 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/10 dark:ring-white/10">
                @if ($active)
                    <x-filament::loading-indicator class="size-5 text-primary-600 dark:text-primary-400" />
                @elseif ($done)
                    <x-filament::icon icon="heroicon-m-check-circle" class="size-6 text-emerald-600 dark:text-emerald-400" />
                @else
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-6 text-red-600 dark:text-red-400" />
                @endif
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ match (true) { $active && $state === 'queued' => 'Website check queued', $active => 'Checking the website', $done => 'Website check finished', default => 'Website check stopped' } }}
                    </p>
                    @if ($active)
                        <span class="text-xs font-medium text-gray-500 tabular-nums dark:text-gray-400">{{ $progress['percent'] }}%</span>
                    @endif
                </div>
                <p class="mt-0.5 truncate text-sm text-gray-600 dark:text-gray-300">{{ $progress['step'] }}</p>

                @if ($active)
                    <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
                        <div @class(['lb-gradient-bar h-full rounded-full transition-all duration-700', 'animate-pulse' => $state === 'queued'])
                             style="width: {{ max(4, $progress['percent']) }}%"></div>
                    </div>
                @endif
            </div>

            @unless ($active)
                <button type="button" wire:click="dismiss" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-950/5 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-200" title="Dismiss">
                    <x-filament::icon icon="heroicon-m-x-mark" class="size-4" />
                </button>
            @endunless
        </div>
    @endif
</div>
