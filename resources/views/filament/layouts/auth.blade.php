@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();

    $features = [
        [
            'icon' => 'heroicon-o-map',
            'title' => 'Map-wide search',
            'body' => 'Search by industry and ZIP, tiled across the area to get past the 60-result cap.',
            'tone' => 'text-cyan-200',
        ],
        [
            'icon' => 'heroicon-o-bolt',
            'title' => 'Website audits on demand',
            'body' => 'Speed, SEO and design issues, ranked major to minor before you write a word.',
            'tone' => 'text-violet-200',
        ],
        [
            'icon' => 'heroicon-o-arrow-path-rounded-square',
            'title' => 'Follow-ups that send themselves',
            'body' => 'A second email after two business days, stopped the moment they reply.',
            'tone' => 'text-emerald-200',
        ],
    ];
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

    <div class="lb-auth lb-aurora-panel min-h-dvh">
        {{-- Horizon line: a soft arc of light low on the page. --}}
        <div class="pointer-events-none absolute inset-x-0 -bottom-[55vh] -z-10 mx-auto h-[80vh] w-[140vw] -translate-x-[20vw] rounded-[100%] bg-[radial-gradient(closest-side,rgba(167,139,250,0.35),transparent)] blur-2xl" aria-hidden="true"></div>

        <div class="mx-auto grid min-h-dvh w-full max-w-7xl gap-12 px-6 py-8 sm:px-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,28rem)] lg:items-center lg:gap-20">
            {{-- Pitch --}}
            <div class="flex flex-col lg:min-h-[calc(100dvh-4rem)] lg:justify-between">
                @include('filament.brand.logo', ['inverse' => true])

                <div class="hidden py-12 lg:block">
                    <p class="lb-glass-dark inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium text-white/80">
                        <span class="size-1.5 rounded-full bg-emerald-300 shadow-[0_0_10px_2px_rgba(110,231,183,0.7)]"></span>
                        Lead generation for web studios
                    </p>

                    <h1 class="mt-6 text-5xl leading-[1.05] font-semibold tracking-tight text-white xl:text-6xl">
                        Your next clients are already
                        <span class="lb-gradient-text">on the map.</span>
                    </h1>

                    <p class="mt-6 max-w-lg text-base leading-7 text-white/65">
                        LeadBreave finds local businesses, shows you exactly what is wrong with their websites, and handles the outreach until they book a call.
                    </p>

                    <ul class="mt-10 grid max-w-xl gap-3">
                        @foreach ($features as $feature)
                            <li class="lb-glass-dark flex items-start gap-4 rounded-2xl p-4">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                    <x-filament::icon :icon="$feature['icon']" class="size-5 {{ $feature['tone'] }}" />
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ $feature['title'] }}</p>
                                    <p class="mt-0.5 text-sm leading-6 text-white/60">{{ $feature['body'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="hidden text-xs text-white/40 lg:block">&copy; {{ now()->year }} {{ config('app.name') }}</p>
            </div>

            {{-- Sign-in card --}}
            <div class="flex flex-col justify-center pb-10 lg:pb-0">
                <div class="rounded-3xl bg-white/[0.97] p-8 shadow-[0_40px_80px_-30px_rgba(15,10,60,0.75)] ring-1 ring-white/40 backdrop-blur-2xl sm:p-10 dark:bg-gray-950/75 dark:ring-white/10">
                    <main id="fi-main-content" tabindex="-1" class="fi-simple-main">
                        {{ $slot }}
                    </main>
                </div>

                <p class="mt-6 text-center text-xs text-white/50">
                    Access is by invitation only. Ask an admin for an account.
                </p>
            </div>
        </div>
    </div>

    {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
</x-filament-panels::layout.base>
