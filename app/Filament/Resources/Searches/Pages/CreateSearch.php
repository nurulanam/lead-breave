<?php

namespace App\Filament\Resources\Searches\Pages;

use App\Enums\SearchStatus;
use App\Filament\Resources\Searches\SearchResource;
use App\Jobs\PlanSearch;
use App\Models\Search;
use App\Support\Countries;
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
        $city = trim((string) ($data['city'] ?? '')) ?: null;
        $zip = trim((string) ($data['zip'] ?? '')) ?: null;
        $country = strtoupper((string) $data['country']);

        return [
            'industry' => trim((string) $data['industry']),
            'country' => $country,
            'city' => $city,
            'zip' => $zip,
            'location' => filled($data['location_label'] ?? null)
                ? (string) $data['location_label']
                : implode(', ', array_filter([$city, $zip, Countries::name($country)])),
            'radius_m' => (int) $data['radius_m'],
            'tiling' => $data['tiling'],
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
