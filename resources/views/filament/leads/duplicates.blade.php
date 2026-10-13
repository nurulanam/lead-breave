<ul class="divide-y divide-gray-950/5 rounded-xl ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
    @foreach ($duplicates as $duplicate)
        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
            <div class="min-w-0">
                <a href="{{ $url($duplicate) }}" class="block truncate text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $duplicate->name }}</a>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ implode(' · ', array_filter([$duplicate->domain, $duplicate->phone, $duplicate->city])) }}</p>
            </div>
            <x-filament::badge :color="$duplicate->status->getColor()">{{ $duplicate->status->getLabel() }}</x-filament::badge>
        </li>
    @endforeach
</ul>
