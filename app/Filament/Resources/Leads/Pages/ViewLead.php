<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadActions;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Lead $record
 */
class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return implode(' · ', array_filter([
            $this->record->category,
            implode(', ', array_filter([$this->record->city, $this->record->region])),
            $this->record->status->label(),
        ])) ?: null;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Overview';
    }

    public function getContentTabIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedBuildingStorefront;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('website')
                ->label('Visit website')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->url(fn (): ?string => $this->record->website, shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled($this->record->website)),
            Action::make('maps')
                ->label('Google Maps')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('gray')
                ->url(fn (): ?string => $this->record->maps_url, shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled($this->record->maps_url)),
            LeadActions::changeStatus(),
            EditAction::make()->icon(Heroicon::OutlinedPencilSquare),
            ActionGroup::make([
                LeadActions::assignOwner(),
                LeadActions::merge(),
                DeleteAction::make()->modalDescription('The lead, its emails, social links and timeline are deleted. This cannot be undone.'),
            ])->icon(Heroicon::OutlinedEllipsisVertical)->color('gray'),
        ];
    }
}
