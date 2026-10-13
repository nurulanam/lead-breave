<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\DataSource;
use App\Enums\LeadSource;
use App\Filament\Resources\Leads\LeadActions;
use App\Filament\Resources\Leads\LeadResource;
use App\Jobs\CheckWebsite;
use App\Models\Lead;
use App\Services\Leads\LeadInput;
use App\Services\Leads\LeadMatcher;
use App\Support\Domain;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected static ?string $title = 'Add a lead';

    protected static bool $canCreateAnother = false;

    /**
     * The existing lead the user was warned about. Submitting again with the
     * same match creates the new lead anyway.
     */
    public ?int $acknowledgedDuplicateId = null;

    private ?string $email = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = LeadInput::normalize($data);
        $this->email = $data['email'] ?? null;
        unset($data['email']);

        return [...$data, 'source' => LeadSource::Manual];
    }

    protected function beforeCreate(): void
    {
        $data = LeadInput::normalize($this->form->getState());
        $existing = app(LeadMatcher::class)->match(
            domain: Domain::fromUrl($data['website'] ?? null),
            phone: $data['phone_e164'] ?? null,
            email: $data['email'] ?? null,
        );

        if ($existing === null || $existing->getKey() === $this->acknowledgedDuplicateId) {
            return;
        }

        $this->acknowledgedDuplicateId = $existing->getKey();

        Notification::make()
            ->title('This looks like a lead you already have')
            ->body(LeadActions::describe($existing).'. Press Create again to add it anyway.')
            ->warning()
            ->persistent()
            ->actions([
                Action::make('open')
                    ->label('Open the existing lead')
                    ->url(LeadResource::getUrl('view', ['record' => $existing]))
                    ->button(),
            ])
            ->send();

        $this->halt();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Lead $lead */
        $lead = parent::handleRecordCreation($data);

        if ($this->email !== null) {
            $lead->emails()->create([
                'email' => $this->email,
                'source' => DataSource::Manual,
                'confidence' => 100,
                'is_primary' => true,
            ]);
        }

        CheckWebsite::forLead($lead);

        return $lead;
    }

    protected function getRedirectUrl(): string
    {
        return LeadResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
