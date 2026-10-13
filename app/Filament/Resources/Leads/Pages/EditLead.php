<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\LeadEventType;
use App\Filament\Resources\Leads\LeadResource;
use App\Jobs\CheckWebsite;
use App\Models\Lead;
use App\Models\User;
use App\Services\Leads\LeadInput;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Lead $record
 */
class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    /** @var list<string> Fields that are derived, so not worth listing as edits. */
    private const DERIVED = ['domain', 'phone_e164', 'updated_at'];

    private ?int $requestedOwnerId = null;

    /**
     * The owner is set through Lead::assignTo(), so the change is logged.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = LeadInput::normalize($data);
        $this->requestedOwnerId = isset($data['owner_id']) ? (int) $data['owner_id'] : null;
        unset($data['owner_id']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Lead $record */
        $record->update($data);
        $changed = array_values(array_diff(array_keys($record->getChanges()), self::DERIVED));

        if ($changed !== []) {
            $record->events()->create([
                'type' => LeadEventType::Edited,
                'user_id' => auth()->id(),
                'data' => ['fields' => $changed],
            ]);
        }

        if (in_array('website', $changed, true)) {
            CheckWebsite::forLead($record);
        }

        $user = auth()->user();
        $record->assignTo(
            $this->requestedOwnerId ? User::query()->find($this->requestedOwnerId) : null,
            $user instanceof User ? $user : null,
        );

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return LeadResource::getUrl('view', ['record' => $this->record]);
    }
}
