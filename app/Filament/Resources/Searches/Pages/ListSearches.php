<?php

namespace App\Filament\Resources\Searches\Pages;

use App\Filament\Resources\Searches\SearchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSearches extends ListRecords
{
    protected static string $resource = SearchResource::class;

    public function getSubheading(): ?string
    {
        return 'Find local businesses on Google Maps by trade and area.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New search')->icon(Heroicon::OutlinedMagnifyingGlass),
        ];
    }
}
