@php
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $file */
    $file = $this->csv;
@endphp

<div
    x-data="{ dragging: false, progress: null }"
    x-on:livewire-upload-start="progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="progress = null"
    x-on:livewire-upload-error="progress = null"
    class="space-y-2"
>
    <p class="text-sm font-medium text-gray-950 dark:text-white">CSV file <span class="text-red-600">*</span></p>

    <label
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false; $event.dataTransfer.files[0] && $wire.upload('csv', $event.dataTransfer.files[0])"
        x-bind:class="dragging ? 'border-primary-500 bg-primary-500/5' : 'border-gray-300 dark:border-white/15'"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition hover:border-primary-400"
    >
        <input type="file" wire:model="csv" accept=".csv,text/csv,text/plain" class="sr-only" />

        @if ($file)
            <x-filament::icon icon="heroicon-o-document-check" class="size-8 text-emerald-500" />
            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $file->getClientOriginalName() }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($file->getSize() / 1024, 1) }} KB · click or drop to choose another file</span>
        @else
            <x-filament::icon icon="heroicon-o-arrow-up-tray" class="size-8 text-gray-400" />
            <span class="text-sm font-medium text-gray-950 dark:text-white">Drop a CSV here, or click to choose one</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Up to 10 MB. Export it from Excel or Google Sheets as "CSV".</span>
        @endif

        <span x-show="progress !== null" x-cloak class="mt-1 block h-1.5 w-48 overflow-hidden rounded-full bg-gray-950/[0.06] dark:bg-white/10">
            <span class="lb-gradient-bar block h-full rounded-full transition-all" x-bind:style="`width: ${progress}%`"></span>
        </span>
    </label>

    @error('csv')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
