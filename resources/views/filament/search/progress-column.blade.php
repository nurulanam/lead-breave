@php
    /** @var \App\Models\Search $record */
    $record = $getRecord();
    $progress = $record->progress();
@endphp

<div class="w-32 px-3 py-2">
    <div class="h-1.5 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
        <div class="lb-gradient-bar h-full rounded-full" style="width: {{ $progress }}%"></div>
    </div>
    <p class="mt-1 text-xs text-gray-500 tabular-nums dark:text-gray-400">
        {{ $record->tiles_done }}/{{ $record->tiles_total }} cells
    </p>
</div>
