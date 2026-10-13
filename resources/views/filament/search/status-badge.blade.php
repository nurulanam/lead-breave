<x-filament::badge :color="$status->getColor()" :icon="$status->getIcon()">
    {{ $status->getLabel() }}
</x-filament::badge>
