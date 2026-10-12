@php($inverse ??= false)

<div class="flex items-center gap-2.5">
    @include('filament.brand.mark', ['attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'size-7 shrink-0 drop-shadow-[0_4px_10px_rgba(139,92,246,0.45)]'])])
    <span @class([
        'text-base font-bold tracking-tight',
        'text-white' => $inverse,
        'text-gray-950 dark:text-white' => ! $inverse,
    ])>LeadBreave</span>
</div>
