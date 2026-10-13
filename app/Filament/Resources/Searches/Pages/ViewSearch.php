<?php

namespace App\Filament\Resources\Searches\Pages;

use App\Enums\SearchStatus;
use App\Filament\Resources\Searches\SearchActions;
use App\Filament\Resources\Searches\SearchResource;
use App\Models\Search;
use App\Services\Search\SearchMapData;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Search $record
 */
class ViewSearch extends ViewRecord
{
    protected static string $resource = SearchResource::class;

    protected string $view = 'filament.resources.searches.pages.view-search';

    public function getTitle(): string|Htmlable
    {
        return SearchResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        $by = $this->record->user?->name;

        return 'Started '.($this->record->started_at ?? $this->record->created_at)->diffForHumans().($by ? " by {$by}" : '');
    }

    public function isInProgress(): bool
    {
        return in_array($this->record->status, [SearchStatus::Pending, SearchStatus::Running], true);
    }

    /**
     * Called by the page's poll while the search runs: reload the counters and
     * push the cells to the map without re-rendering it.
     */
    public function refreshProgress(): void
    {
        $this->record->refresh();
        $this->dispatch('search-map-updated', data: SearchMapData::forSearch($this->record));
    }

    protected function getHeaderActions(): array
    {
        return [
            SearchActions::stop(),
            SearchActions::rerun(),
            DeleteAction::make()->modalDescription('The search and its cells are deleted. Leads it found are kept.'),
        ];
    }
}
