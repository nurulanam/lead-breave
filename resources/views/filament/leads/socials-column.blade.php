@php
    /** @var \App\Models\Lead $record */
    $record = $getRecord();
    $chips = [
        'facebook' => ['FB', 'bg-[#1877F2]'],
        'instagram' => ['IG', 'bg-gradient-to-br from-[#F58529] via-[#DD2A7B] to-[#8134AF]'],
        'linkedin' => ['in', 'bg-[#0A66C2]'],
        'x' => ['X', 'bg-gray-950 dark:bg-gray-700'],
        'tiktok' => ['TT', 'bg-gray-950 dark:bg-gray-700'],
        'youtube' => ['YT', 'bg-[#FF0000]'],
        'pinterest' => ['P', 'bg-[#E60023]'],
        'yelp' => ['Y', 'bg-[#D32323]'],
        'nextdoor' => ['N', 'bg-[#8ED500]'],
        'google_business' => ['G', 'bg-[#4285F4]'],
    ];
@endphp

<div class="flex flex-wrap gap-1 px-3 py-2">
    @forelse ($record->socials as $social)
        <a href="{{ $social->url }}" target="_blank" rel="noopener noreferrer" title="{{ $social->platform->label() }}"
           class="flex h-5 min-w-5 items-center justify-center rounded px-1 text-[10px] font-bold text-white {{ $chips[$social->platform->value][1] ?? 'bg-gray-500' }}">
            {{ $chips[$social->platform->value][0] ?? '?' }}
        </a>
    @empty
        <span class="text-sm text-gray-400">—</span>
    @endforelse
</div>
