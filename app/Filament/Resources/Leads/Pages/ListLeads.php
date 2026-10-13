<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\LeadStatus;
use App\Filament\Pages\ImportLeads;
use App\Filament\Pages\NeedsInfoQueue;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    /**
     * Pipeline groups shown as tabs.
     *
     * @var array<string, array{label: string, statuses: list<LeadStatus>}>
     */
    private const GROUPS = [
        'progress' => ['label' => 'In progress', 'statuses' => [LeadStatus::Enriching, LeadStatus::Enriched, LeadStatus::Triaged, LeadStatus::Audited, LeadStatus::Ready]],
        'contacted' => ['label' => 'Contacted', 'statuses' => [LeadStatus::Contacted, LeadStatus::FollowedUp, LeadStatus::Replied, LeadStatus::MeetingBooked]],
        'closed' => ['label' => 'Closed', 'statuses' => [LeadStatus::Won, LeadStatus::Lost, LeadStatus::NotAFit, LeadStatus::Unsubscribed, LeadStatus::Bounced]],
    ];

    public function getSubheading(): string
    {
        return 'Every business you have found, imported or added by hand.';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')
                ->badge(fn (): int => Lead::query()->count())
                ->deferBadge(),
            'mine' => Tab::make('Mine')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('owner_id', auth()->id()))
                ->badge(fn (): int => Lead::query()->where('owner_id', auth()->id())->count())
                ->deferBadge(),
            'new' => Tab::make('New')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LeadStatus::New))
                ->badge(fn (): int => Lead::query()->where('status', LeadStatus::New)->count())
                ->deferBadge(),
            'needs_info' => Tab::make('Needs info')
                ->modifyQueryUsing(fn (Builder $query) => Lead::whereNeedsInfo($query))
                ->badge(fn (): int => Lead::query()->needsInfo()->count())
                ->badgeColor('warning')
                ->deferBadge(),
        ];

        foreach (self::GROUPS as $key => $group) {
            $tabs[$key] = Tab::make($group['label'])
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $group['statuses']))
                ->badge(fn (): int => Lead::query()->whereIn('status', $group['statuses'])->count())
                ->deferBadge();
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('needsInfoQueue')
                ->label('Fill in missing info')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->url(fn (): string => NeedsInfoQueue::getUrl()),
            Action::make('import')
                ->label('Import CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(fn (): string => ImportLeads::getUrl()),
            CreateAction::make()->label('Add lead')->icon(Heroicon::OutlinedPlus),
        ];
    }
}
