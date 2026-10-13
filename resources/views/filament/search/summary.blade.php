{{-- @var \App\Models\Search $search --}}
@php
    $progress = $search->progress();
    $duration = $search->started_at ? $search->started_at->diffForHumans($search->completed_at ?? now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) : '—';
    $stats = [
        ['label' => 'Businesses found', 'value' => number_format($search->results_found)],
        ['label' => 'New leads', 'value' => number_format($search->leads_created)],
        ['label' => 'Already known', 'value' => number_format($search->leads_linked)],
        ['label' => 'Google requests', 'value' => number_format($search->api_cost_units), 'caption' => '≈ $'.number_format($cost, 2)],
        ['label' => 'Duration', 'value' => $duration],
    ];
@endphp

<div class="space-y-5">
    <div>
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-gray-950 dark:text-white">
                @if ($search->status === \App\Enums\SearchStatus::Pending)
                    Waiting for a worker…
                @else
                    {{ number_format($search->tiles_done) }} of {{ number_format($search->tiles_total) }} cells searched
                @endif
            </span>
            <span class="text-gray-500 tabular-nums dark:text-gray-400">{{ $progress }}%</span>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
            <div class="lb-gradient-bar h-full rounded-full transition-all duration-700" style="width: {{ $progress }}%"></div>
        </div>
    </div>

    @if ($search->status === \App\Enums\SearchStatus::Pending && $search->updated_at?->lt(now()->subSeconds(20)))
        <div class="flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/15 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20">
            <x-filament::icon icon="heroicon-m-clock" class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-medium">No worker has picked this search up yet.</p>
                <p class="mt-0.5">Searches run in the background. Start a worker with <code class="rounded bg-amber-900/10 px-1 font-mono text-xs dark:bg-white/10">composer dev</code>, Horizon, or <code class="rounded bg-amber-900/10 px-1 font-mono text-xs dark:bg-white/10">php artisan queue:work --queue={{ \App\Providers\AppServiceProvider::QUEUES }}</code>. The map updates as soon as it starts.</p>
            </div>
        </div>
    @endif

    @if ($search->stop_reason)
        <div @class([
            'flex items-start gap-3 rounded-xl px-4 py-3 text-sm ring-1',
            'bg-red-50 text-red-800 ring-red-600/15 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20' => $search->status === \App\Enums\SearchStatus::Failed,
            'bg-amber-50 text-amber-900 ring-amber-600/15 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20' => $search->status !== \App\Enums\SearchStatus::Failed,
        ])>
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
            <p>{{ $search->stop_reason }}</p>
        </div>
    @endif

    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ($stats as $stat)
            <div class="rounded-xl bg-gray-950/[0.03] px-4 py-3 dark:bg-white/5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $stat['value'] }}</dd>
                @isset($stat['caption'])
                    <dd class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['caption'] }}</dd>
                @endisset
            </div>
        @endforeach
    </dl>
</div>
