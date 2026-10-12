@php
    $tones = [
        'cyan' => 'text-cyan-200 bg-cyan-400/15 ring-cyan-300/30',
        'violet' => 'text-violet-200 bg-violet-400/15 ring-violet-300/30',
        'fuchsia' => 'text-fuchsia-200 bg-fuchsia-400/15 ring-fuchsia-300/30',
        'emerald' => 'text-emerald-200 bg-emerald-400/15 ring-emerald-300/30',
    ];
@endphp

<x-filament-widgets::widget>
    <section class="lb-aurora-panel rounded-3xl px-6 py-7 text-white shadow-[0_30px_60px_-30px_rgba(76,29,149,0.55)] ring-1 ring-white/10 sm:px-8 sm:py-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="lb-glass-dark inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium text-white/80">
                    <x-filament::icon icon="heroicon-m-calendar" class="size-3.5 text-cyan-200" />
                    {{ $date }}
                </p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ $greeting }}, <span class="lb-gradient-text">{{ $name }}</span>
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-white/65">
                    Your prospecting at a glance: who you found, who you reached and who said yes.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($setup && $setup['done'] < $setup['total'])
                    <a href="{{ $setupUrl }}" class="lb-glass-dark group inline-flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium text-white transition hover:bg-white/15">
                        <span class="relative inline-flex size-8 items-center justify-center">
                            @php($percent = (int) round($setup['done'] / max($setup['total'], 1) * 100))
                            <svg viewBox="0 0 36 36" class="absolute inset-0 size-8 -rotate-90">
                                <circle cx="18" cy="18" r="15" fill="none" stroke="rgb(255 255 255 / 0.15)" stroke-width="3.5" />
                                <circle cx="18" cy="18" r="15" fill="none" stroke="#a7f3d0" stroke-width="3.5" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $percent }} 100" />
                            </svg>
                            <span class="text-[10px] font-bold">{{ $setup['done'] }}/{{ $setup['total'] }}</span>
                        </span>
                        Finish setup
                        <x-filament::icon icon="heroicon-m-arrow-right" class="size-4 text-white/60 transition group-hover:translate-x-0.5 group-hover:text-white" />
                    </a>
                @endif
            </div>
        </div>

        <dl class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($kpis as $kpi)
                <div class="lb-glass-dark rounded-2xl p-4">
                    <div class="flex items-center justify-between">
                        <dt class="text-xs font-medium tracking-wide text-white/60 uppercase">{{ $kpi['label'] }}</dt>
                        <span class="flex size-7 items-center justify-center rounded-lg ring-1 {{ $tones[$kpi['tone']] }}">
                            <x-filament::icon :icon="$kpi['icon']" class="size-4" />
                        </span>
                    </div>
                    <dd class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $kpi['value'] }}</dd>
                    <dd class="mt-1 text-xs text-white/50">{{ $kpi['caption'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</x-filament-widgets::widget>
