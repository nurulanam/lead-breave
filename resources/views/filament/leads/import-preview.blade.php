@if ($preview)
    @php
        $cards = [
            ['label' => 'Rows', 'value' => $preview['total'], 'class' => 'text-gray-950 dark:text-white'],
            ['label' => 'New leads', 'value' => $preview['new'], 'class' => 'text-emerald-600 dark:text-emerald-400'],
            ['label' => 'Already known', 'value' => $preview['duplicate'], 'class' => 'text-sky-600 dark:text-sky-400'],
            ['label' => 'Will be skipped', 'value' => $preview['invalid'], 'class' => 'text-amber-600 dark:text-amber-400'],
        ];
        $badges = ['new' => 'success', 'duplicate' => 'info', 'invalid' => 'warning'];
        $labels = ['new' => 'New', 'duplicate' => 'Known', 'invalid' => 'Skipped'];
    @endphp

    <div class="space-y-5">
        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($cards as $card)
                <div class="rounded-xl bg-gray-950/[0.03] px-4 py-3 dark:bg-white/5">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums {{ $card['class'] }}">{{ number_format($card['value']) }}</dd>
                </div>
            @endforeach
        </dl>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            Known businesses are matched by website, phone or email. Their empty fields are filled in from the file; nothing they already have is overwritten.
        </p>

        <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-950/[0.03] text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">Line</th>
                        <th class="px-4 py-2 text-start font-medium">Business</th>
                        <th class="px-4 py-2 text-start font-medium">Result</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
                    @foreach ($preview['rows'] as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-500 tabular-nums dark:text-gray-400">{{ $row['line'] }}</td>
                            <td class="px-4 py-2 font-medium text-gray-950 dark:text-white">{{ $row['name'] ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="flex items-center gap-2">
                                    <x-filament::badge :color="$badges[$row['outcome']]" size="sm">{{ $labels[$row['outcome']] }}</x-filament::badge>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['detail'] }}</span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Showing the first {{ count($preview['rows']) }} rows.</p>
    </div>
@endif
