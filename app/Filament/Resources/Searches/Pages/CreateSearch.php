<?php

namespace App\Filament\Resources\Searches\Pages;

use App\Enums\SearchStatus;
use App\Filament\Resources\Searches\SearchResource;
use App\Jobs\PlanSearch;
use App\Models\Search;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateSearch extends CreateRecord
{
    protected static string $resource = SearchResource::class;

    protected static ?string $title = 'New search';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $location = trim((string) $data['location']);

        return [
            ...$data,
            'industry' => trim((string) $data['industry']),
            'location' => $location,
            'zip' => preg_match('/^\d{5}$/', $location) === 1 ? $location : null,
            'center_lat' => is_numeric($data['center_lat'] ?? null) ? (float) $data['center_lat'] : null,
            'center_lng' => is_numeric($data['center_lng'] ?? null) ? (float) $data['center_lng'] : null,
            'user_id' => auth()->id(),
            'status' => SearchStatus::Pending,
        ];
    }

    protected function afterCreate(): void
    {
        /** @var Search $search */
        $search = $this->getRecord();

        PlanSearch::dispatch($search->id);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Run search')->icon('heroicon-m-magnifying-glass');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Search queued';
    }

    protected function getRedirectUrl(): string
    {
        return SearchResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
