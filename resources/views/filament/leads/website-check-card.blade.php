@php
    /** @var \App\Models\WebsiteCheck $record */
    $record = $getRecord();
    $findings = \App\Services\Website\WebsiteFindings::for($record);
    $summary = \App\Services\Website\WebsiteFindings::summary($record);
    $hasFails = $record->failed() || collect($findings)->contains('state', 'fail');
    $hasWarns = collect($findings)->contains('state', 'warn');
    $marks = [
        'pass' => ['icon' => 'heroicon-m-check-circle', 'class' => 'text-emerald-500'],
        'warn' => ['icon' => 'heroicon-m-exclamation-triangle', 'class' => 'text-amber-500'],
        'fail' => ['icon' => 'heroicon-m-x-circle', 'class' => 'text-red-500'],
        'info' => ['icon' => 'heroicon-m-information-circle', 'class' => 'text-gray-400'],
    ];
@endphp

<div class="w-full px-4 py-3">
    <div class="flex items-start gap-3">
        <span @class([
            'flex size-9 shrink-0 items-center justify-center rounded-lg ring-1',
            'bg-red-500/10 text-red-600 ring-red-500/20 dark:text-red-400' => $hasFails,
            'bg-amber-500/10 text-amber-600 ring-amber-500/20 dark:text-amber-400' => ! $hasFails && $hasWarns,
            'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20 dark:text-emerald-400' => ! $hasFails && ! $hasWarns,
        ])>
            <x-filament::icon :icon="$record->type->icon()" class="size-5" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $record->type->label() }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $summary }} · checked {{ $record->checked_at?->diffForHumans() ?? 'never' }}</p>
        </div>
    </div>

    <ul class="mt-3 space-y-1.5">
        @foreach ($findings as $finding)
            <li class="flex items-start gap-2 text-sm">
                <x-filament::icon :icon="$marks[$finding['state']]['icon']" class="mt-0.5 size-4 shrink-0 {{ $marks[$finding['state']]['class'] }}" />
                <span class="min-w-0 flex-1 text-gray-700 dark:text-gray-200">{{ $finding['label'] }}</span>
                @if ($finding['value'] !== null)
                    <span class="max-w-[55%] truncate text-end text-xs text-gray-500 dark:text-gray-400" title="{{ $finding['value'] }}">{{ $finding['value'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
