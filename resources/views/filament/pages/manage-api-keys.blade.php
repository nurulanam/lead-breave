@php
    $statuses = [
        'connected' => ['label' => 'Connected', 'color' => 'success', 'icon' => 'heroicon-m-check-circle'],
        'partial' => ['label' => 'Partly working', 'color' => 'warning', 'icon' => 'heroicon-m-exclamation-triangle'],
        'failing' => ['label' => 'Not working', 'color' => 'danger', 'icon' => 'heroicon-m-x-circle'],
        'untested' => ['label' => 'Not tested', 'color' => 'gray', 'icon' => 'heroicon-m-question-mark-circle'],
        'missing' => ['label' => 'Not connected', 'color' => 'gray', 'icon' => 'heroicon-m-minus-circle'],
    ];
    $brand = [
        'google_api_key' => ['icon' => 'heroicon-o-map', 'tile' => 'bg-gradient-to-br from-sky-400 to-blue-600'],
        'anthropic_key' => ['icon' => 'heroicon-o-sparkles', 'tile' => 'bg-gradient-to-br from-amber-400 to-orange-600'],
    ];
    $usage = $this->googleUsage();
@endphp

<x-filament-panels::page>
    <div class="flex items-start gap-3 rounded-2xl bg-white/60 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-950/5 backdrop-blur dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
        <x-filament::icon icon="heroicon-m-lock-closed" class="mt-0.5 size-4 shrink-0 text-primary-500" />
        <p>
            Keys are encrypted with this app's key before they are stored, and are never sent back to the browser.
            Only the first and last four characters are shown here. Only admins can see this page.
        </p>
    </div>

    @foreach ($this->integrations() as $integration)
        @php
            /** @var \App\Enums\ApiKey $key */
            $key = $integration['key'];
            $status = $statuses[$integration['status']];
            $arguments = ['key' => $key->value];
        @endphp

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm {{ $brand[$key->value]['tile'] }}">
                        <x-filament::icon :icon="$brand[$key->value]['icon']" class="size-5" />
                    </span>
                    <span>
                        <span class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                            {{ $key->label() }}
                            <span class="rounded-md bg-gray-950/5 px-1.5 py-0.5 text-[11px] font-medium text-gray-500 dark:bg-white/10 dark:text-gray-400">{{ $key->isRequired() ? 'Required' : 'Optional' }}</span>
                        </span>
                        <span class="block text-sm font-normal text-gray-500 dark:text-gray-400">{{ $key->description() }}</span>
                    </span>
                </div>
            </x-slot>

            <x-slot name="afterHeader">
                <x-filament::badge :color="$status['color']" :icon="$status['icon']">
                    {{ $status['label'] }}
                </x-filament::badge>
            </x-slot>

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="space-y-5 lg:col-span-3">
                    {{-- The saved key --}}
                    <div class="rounded-xl bg-gray-950/[0.03] p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">API key</p>
                                @if ($integration['masked'])
                                    <p class="mt-1 font-mono text-sm tracking-wide text-gray-950 dark:text-white">{{ $integration['masked'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($integration['updatedAt'])
                                            Saved {{ $integration['updatedAt']->diffForHumans() }}{{ $integration['updatedBy'] ? ' by '.$integration['updatedBy'] : '' }}.
                                        @else
                                            Saved before change history was kept.
                                        @endif
                                        @if ($integration['checkedAt'])
                                            Tested {{ $integration['checkedAt']->diffForHumans() }}.
                                        @endif
                                    </p>
                                @else
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No key saved yet.</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($integration['masked'])
                                    {{ ($this->testKeyAction)($arguments) }}
                                @endif
                                {{ ($this->replaceKeyAction)($arguments) }}
                                @if ($integration['masked'])
                                    {{ ($this->removeKeyAction)($arguments) }}
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- What the key must reach --}}
                    <div>
                        <p class="mb-2 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ count($integration['services']) > 1 ? 'APIs this key needs' : 'API this key needs' }}</p>
                        <ul class="divide-y divide-gray-950/5 rounded-xl ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
                            @foreach ($integration['services'] as $service)
                                <li class="flex items-start gap-3 px-4 py-3">
                                    <span @class([
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        'bg-emerald-500' => $service['ok'] === true,
                                        'bg-red-500' => $service['ok'] === false,
                                        'bg-gray-300 dark:bg-gray-600' => $service['ok'] === null,
                                    ])></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $service['label'] }}</p>
                                            <p @class([
                                                'text-xs font-medium',
                                                'text-emerald-600 dark:text-emerald-400' => $service['ok'] === true,
                                                'text-red-600 dark:text-red-400' => $service['ok'] === false,
                                                'text-gray-400' => $service['ok'] === null,
                                            ])>{{ match ($service['ok']) { true => 'Working', false => 'Failed', null => 'Not tested' } }}</p>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $service['purpose'] }}</p>
                                        @if ($service['ok'] === false && $service['message'])
                                            <p class="mt-1.5 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs text-red-800 dark:bg-red-500/10 dark:text-red-300">{{ $service['message'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="space-y-5 lg:col-span-2">
                    @if ($key === \App\Enums\ApiKey::Google)
                        <div class="rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <div class="flex items-baseline justify-between">
                                <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">This month</p>
                                <a href="{{ \App\Filament\Pages\ManageSearchSettings::getUrl() }}" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">Limits</a>
                            </div>
                            <dl class="mt-3 space-y-3 text-sm">
                                <div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-600 dark:text-gray-300">Lead search requests</dt>
                                        <dd class="font-medium text-gray-950 tabular-nums dark:text-white">{{ number_format($usage['places']) }} <span class="font-normal text-gray-400">/ {{ number_format($usage['cap']) }}</span></dd>
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
                                        <div class="lb-gradient-bar h-full rounded-full" style="width: {{ min(100, $usage['cap'] > 0 ? round($usage['places'] / $usage['cap'] * 100) : 0) }}%"></div>
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600 dark:text-gray-300">Location lookups</dt>
                                    <dd class="font-medium text-gray-950 tabular-nums dark:text-white">{{ number_format($usage['geocoding']) }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-950/5 pt-3 dark:border-white/10">
                                    <dt class="text-gray-600 dark:text-gray-300">Estimated cost</dt>
                                    <dd class="font-semibold text-gray-950 tabular-nums dark:text-white">${{ number_format($usage['cost'], 2) }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endif

                    <details class="group rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10" @if ($integration['status'] === 'missing') open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-medium text-gray-950 dark:text-white">
                            How to get this key
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 text-gray-400 transition group-open:rotate-180" />
                        </summary>
                        <ol class="space-y-2.5 border-t border-gray-950/5 px-4 py-3 dark:border-white/10">
                            @foreach ($key->setupSteps() as $step)
                                <li class="flex gap-3 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-[11px] font-semibold text-primary-700 dark:text-primary-300">{{ $loop->iteration }}</span>
                                    <span>
                                        {{ $step['text'] }}
                                        @isset($step['url'])
                                            <a href="{{ $step['url'] }}" target="_blank" rel="noopener noreferrer" class="ms-1 inline-flex items-center gap-0.5 font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                Open
                                                <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-3" />
                                            </a>
                                        @endisset
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </details>
                </div>
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
