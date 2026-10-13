<?php

namespace App\Filament\Pages;

use App\Enums\DataSource;
use App\Enums\LeadEventType;
use App\Enums\LeadStatus;
use App\Enums\Permission;
use App\Filament\Resources\Leads\RelationManagers\SocialsRelationManager;
use App\Models\Lead;
use App\Models\User;
use App\Services\Leads\LeadInput;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Work through leads missing a website or email, one at a time, best
 * prospects first.
 *
 * @property-read Schema $form
 */
class NeedsInfoQueue extends Page
{
    protected string $view = 'filament.pages.needs-info-queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Prospecting';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Needs info';

    protected static ?string $title = 'Fill in missing info';

    public ?int $leadId = null;

    /** @var list<int> Leads skipped in this session. */
    public array $skipped = [];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::EditLeads->value) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Lead::query()->needsInfo()->count();

        return $count > 0 ? number_format($count) : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public function mount(): void
    {
        $this->loadNext();
    }

    public function getSubheading(): string
    {
        return 'Leads with no website or no email. Find the details, save, and the next one loads.';
    }

    public function lead(): ?Lead
    {
        return $this->leadId ? Lead::query()->with(['emails', 'socials'])->find($this->leadId) : null;
    }

    public function remaining(): int
    {
        return Lead::query()->needsInfo()->whereNotIn('id', $this->skipped)->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                TextInput::make('website')
                    ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                    ->placeholder('example.com')
                    ->maxLength(2048)
                    ->rule('regex:/^(https?:\/\/)?[^\s\/$.?#]+\.[^\s]+$/i')
                    ->validationMessages(['regex' => 'Enter a web address, such as example.com.'])
                    ->autofocus(),
                TextInput::make('email')
                    ->email()
                    ->prefixIcon(Heroicon::OutlinedEnvelope)
                    ->placeholder('owner@example.com')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->prefixIcon(Heroicon::OutlinedPhone)
                    ->maxLength(50),
                TextInput::make('social')
                    ->label('Facebook or Instagram link')
                    ->url()
                    ->prefixIcon(Heroicon::OutlinedShare)
                    ->maxLength(512),
            ]);
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Save and next')
            ->icon(Heroicon::OutlinedCheck)
            ->keyBindings(['mod+enter'])
            ->action(fn () => $this->save());
    }

    public function skipAction(): Action
    {
        return Action::make('skip')
            ->label('Skip')
            ->icon(Heroicon::OutlinedForward)
            ->color('gray')
            ->keyBindings(['mod+right'])
            ->action(function (): void {
                if ($this->leadId) {
                    $this->skipped[] = $this->leadId;
                }

                $this->loadNext();
            });
    }

    public function notAFitAction(): Action
    {
        return Action::make('notAFit')
            ->label('Not a fit')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->outlined()
            ->visible(fn (): bool => $this->lead()?->status->canTransitionTo(LeadStatus::NotAFit) ?? false)
            ->action(function (): void {
                $user = auth()->user();
                $this->lead()?->transitionTo(LeadStatus::NotAFit, $user instanceof User ? $user : null, ['reason' => 'Marked from the needs-info queue']);

                Notification::make()->title('Marked as not a fit')->success()->send();
                $this->loadNext();
            });
    }

    public function save(): void
    {
        $lead = $this->lead();

        if ($lead === null) {
            return;
        }

        $data = LeadInput::normalize([...$this->form->getState(), 'country' => $lead->country ?? 'US']);
        $changed = [];

        if ($data['website'] !== null && $data['website'] !== $lead->website) {
            $lead->website = $data['website'];
            $changed[] = 'website';
        }

        if ($data['phone'] !== null && $data['phone_e164'] !== $lead->phone_e164) {
            $lead->fill(['phone' => $data['phone'], 'phone_e164' => $data['phone_e164']]);
            $changed[] = 'phone';
        }

        $lead->save();

        if ($data['email'] !== null && ! $lead->emails->contains('email', $data['email'])) {
            $email = $lead->emails()->create(['email' => $data['email'], 'source' => DataSource::Manual, 'confidence' => 100]);

            if (! $lead->emails->contains('is_primary', true)) {
                $email->makePrimary();
            }

            $changed[] = 'email';
        }

        if ($data['social'] !== null && ! $lead->socials->contains('url', $data['social'])) {
            $platform = SocialsRelationManager::platformFor($data['social']);

            if ($platform !== null) {
                $lead->socials()->create(['platform' => $platform, 'url' => $data['social'], 'source' => DataSource::Manual]);
                $changed[] = 'social link';
            }
        }

        if ($changed !== []) {
            $lead->events()->create(['type' => LeadEventType::Edited, 'user_id' => auth()->id(), 'data' => ['fields' => $changed]]);
        }

        Notification::make()->title($changed === [] ? 'Nothing to save' : 'Saved '.$lead->name)->{$changed === [] ? 'warning' : 'success'}()->send();

        if ($changed === []) {
            return;
        }

        if (Lead::query()->needsInfo()->whereKey($lead->getKey())->exists()) {
            $this->skipped[] = $lead->getKey();
        }

        $this->loadNext();
    }

    private function loadNext(): void
    {
        $lead = Lead::query()
            ->needsInfo()
            ->whereNotIn('id', $this->skipped)
            ->orderByRaw('fit_score is null')
            ->orderByDesc('fit_score')
            ->orderByDesc('review_count')
            ->orderBy('id')
            ->first();

        $this->leadId = $lead?->getKey();
        $this->form->fill([
            'website' => $lead?->website,
            'phone' => $lead?->phone,
            'email' => null,
            'social' => null,
        ]);
    }
}
