@php
    $imports = $this->recentImports();
    $statusColors = ['pending' => 'gray', 'running' => 'primary', 'completed' => 'success', 'failed' => 'danger'];
@endphp

<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}
    </form>

    <x-filament::section>
        <x-slot name="heading">Recent imports</x-slot>

        <div @if ($this->hasRunningImport()) wire:poll.3s @endif>
            @forelse ($imports as $import)
                <details class="group border-b border-gray-950/5 py-3 last:border-0 dark:border-white/5">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $import->original_name }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $import->created_at->diffForHumans() }}{{ $import->user ? ' by '.$import->user->name : '' }}</span>
                        </span>
                        <span class="flex items-center gap-3 text-xs text-gray-600 tabular-nums dark:text-gray-300">
                            <span><strong class="text-emerald-600 dark:text-emerald-400">{{ number_format($import->created_count) }}</strong> new</span>
                            <span><strong class="text-sky-600 dark:text-sky-400">{{ number_format($import->updated_count) }}</strong> updated</span>
                            <span><strong class="text-amber-600 dark:text-amber-400">{{ number_format($import->invalid_count) }}</strong> skipped</span>
                        </span>
                        @if (! $import->isFinished())
                            <span class="w-28">
                                <span class="block h-1.5 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
                                    <span class="lb-gradient-bar block h-full rounded-full" style="width: {{ $import->progress() }}%"></span>
                                </span>
                            </span>
                        @endif
                        <x-filament::badge :color="$statusColors[$import->status] ?? 'gray'">{{ ucfirst($import->status) }}</x-filament::badge>
                    </summary>

                    @if ($import->errors)
                        <ul class="mt-3 max-h-48 space-y-1 overflow-y-auto rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                            @foreach ($import->errors as $error)
                                <li>Line {{ $error['row'] }}: {{ $error['reason'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </details>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No imports yet.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
