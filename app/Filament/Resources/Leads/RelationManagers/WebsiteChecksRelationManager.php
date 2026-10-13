<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\LeadStatus;
use App\Jobs\CheckWebsite;
use App\Models\Lead;
use App\Models\WebsiteCheck;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The lead's website checks as cards: reachability and contacts, HTTPS,
 * on-page SEO and technology. Each card can be re-checked on its own.
 */
class WebsiteChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'websiteChecks';

    protected static ?string $title = 'Website';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedGlobeAlt;

    /** Set after queuing a check, so the cards refresh until results arrive. */
    public bool $waitingForResults = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var Lead $ownerRecord */
        $problems = $ownerRecord->websiteChecks()->where('status', WebsiteCheck::STATUS_FAILED)->count();

        return Tab::make('Website')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->badge($problems ?: null)
            ->badgeColor('danger');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw(
                "case type when 'crawl' then 1 when 'ssl' then 2 when 'seo' then 3 else 4 end"
            ))
            ->paginated(false)
            ->poll(fn (): ?string => $this->waitingForResults || $this->lead()->status === LeadStatus::Enriching ? '4s' : null)
            ->contentGrid(['md' => 2])
            ->columns([
                Stack::make([
                    ViewColumn::make('card')->view('filament.leads.website-check-card'),
                ]),
            ])
            ->headerActions([
                Action::make('checkAll')
                    ->label(fn (): string => $this->lead()->websiteChecks()->exists() ? 'Check again' : 'Check website')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => filled($this->lead()->website))
                    ->action(function (): void {
                        CheckWebsite::forLead($this->lead());
                        $this->waitingForResults = true;

                        Notification::make()->title('Website check queued')->body('Results appear here in about a minute.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('recheck')
                    ->label('Re-check')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->size('sm')
                    ->action(function (WebsiteCheck $record): void {
                        CheckWebsite::forLead($this->lead(), $record->type);
                        $this->waitingForResults = true;

                        Notification::make()->title($record->type->label().' check queued')->success()->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-globe-alt')
            ->emptyStateHeading(fn (): string => filled($this->lead()->website) ? 'Not checked yet' : 'No website to check')
            ->emptyStateDescription(fn (): string => filled($this->lead()->website)
                ? 'Checks run automatically for new leads. Press "Check website" to run one now.'
                : 'Add a website to this lead and it will be checked automatically.');
    }

    private function lead(): Lead
    {
        $lead = $this->getOwnerRecord();

        return $lead instanceof Lead ? $lead : abort(404);
    }
}
