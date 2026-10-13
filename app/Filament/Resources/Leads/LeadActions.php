<?php

namespace App\Filament\Resources\Leads;

use App\Enums\LeadStatus;
use App\Exceptions\InvalidLeadTransition;
use App\Jobs\CheckWebsite;
use App\Models\Lead;
use App\Models\User;
use App\Services\Leads\LeadMerger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Status, owner and merge actions, shared by the leads table and the lead page.
 */
class LeadActions
{
    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Lead $record): bool => $record->status->transitions() !== [])
            ->modalWidth('md')
            ->schema(fn (Lead $record): array => [
                Select::make('status')
                    ->label('Move to')
                    ->options(collect($record->status->transitions())->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->label()]))
                    ->required()
                    ->native(false),
            ])
            ->action(function (Lead $record, array $data): void {
                $record->transitionTo(LeadStatus::from($data['status']), self::user());

                Notification::make()->title('Moved to '.LeadStatus::from($data['status'])->label())->success()->send();
            });
    }

    public static function bulkChangeStatus(): BulkAction
    {
        return BulkAction::make('bulkChangeStatus')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->authorizeIndividualRecords('update')
            ->modalWidth('md')
            ->modalDescription('Each lead moves only if its current status allows it. The rest are left as they are.')
            ->schema([
                Select::make('status')
                    ->label('Move to')
                    ->options(LeadStatus::class)
                    ->required()
                    ->native(false),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $target = LeadStatus::from($data['status'] instanceof LeadStatus ? $data['status']->value : $data['status']);
                $moved = 0;

                foreach ($records as $lead) {
                    if (! $lead instanceof Lead) {
                        continue;
                    }

                    try {
                        $lead->transitionTo($target, self::user());
                        $moved++;
                    } catch (InvalidLeadTransition) {
                        continue;
                    }
                }

                $skipped = $records->count() - $moved;

                Notification::make()
                    ->title("Moved {$moved} ".str('lead')->plural($moved).' to '.$target->label())
                    ->body($skipped > 0 ? "{$skipped} could not move there from their current status." : null)
                    ->{$skipped > 0 ? 'warning' : 'success'}()
                    ->send();
            });
    }

    public static function assignOwner(): Action
    {
        return Action::make('assignOwner')
            ->label('Assign owner')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('gray')
            ->authorize('update')
            ->modalWidth('md')
            ->fillForm(fn (Lead $record): array => ['owner_id' => $record->owner_id])
            ->schema([self::ownerSelect()])
            ->action(function (Lead $record, array $data): void {
                $record->assignTo(self::owner($data), self::user());

                Notification::make()->title('Owner updated')->success()->send();
            });
    }

    public static function bulkAssignOwner(): BulkAction
    {
        return BulkAction::make('bulkAssignOwner')
            ->label('Assign owner')
            ->icon(Heroicon::OutlinedUserCircle)
            ->authorizeIndividualRecords('update')
            ->modalWidth('md')
            ->schema([self::ownerSelect()])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $owner = self::owner($data);
                foreach ($records as $lead) {
                    if ($lead instanceof Lead) {
                        $lead->assignTo($owner, self::user());
                    }
                }

                Notification::make()
                    ->title($owner ? "Assigned {$records->count()} ".str('lead')->plural($records->count())." to {$owner->name}" : 'Leads unassigned')
                    ->success()
                    ->send();
            });
    }

    public static function merge(): Action
    {
        return Action::make('merge')
            ->label('Merge duplicate')
            ->icon(Heroicon::OutlinedArrowsPointingIn)
            ->color('gray')
            ->authorize('update')
            ->modalWidth('lg')
            ->modalDescription('Combine two leads for the same business. Emails, social links, searches and the timeline move to the lead you keep. The kept lead\'s details win; the other only fills gaps.')
            ->schema(fn (Lead $record): array => [
                Select::make('other_id')
                    ->label('The duplicate')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search): array => Lead::query()
                        ->whereKeyNot($record->getKey())
                        ->where(fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('domain', 'like', "%{$search}%")
                            ->orWhere('phone_e164', 'like', '%'.preg_replace('/\D/', '', $search).'%')
                            ->orWhere('city', 'like', "%{$search}%"))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Lead $lead): array => [$lead->id => self::describe($lead)])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ($lead = Lead::query()->find($value)) ? self::describe($lead) : null),
                Radio::make('keep')
                    ->label('Keep')
                    ->options(['this' => 'This lead ('.$record->name.')', 'other' => 'The duplicate'])
                    ->default('this')
                    ->required(),
            ])
            ->modalSubmitActionLabel('Merge')
            ->action(function (Lead $record, array $data, Action $action): void {
                $other = Lead::query()->findOrFail($data['other_id']);
                [$keep, $duplicate] = $data['keep'] === 'other' ? [$other, $record] : [$record, $other];

                $kept = app(LeadMerger::class)->merge($keep, $duplicate, self::user());

                Notification::make()->title('Leads merged')->body("Kept {$kept->name}.")->success()->send();

                if ($kept->isNot($record)) {
                    $action->redirect(LeadResource::getUrl('view', ['record' => $kept]));
                }
            });
    }

    public static function bulkCheckWebsites(): BulkAction
    {
        return BulkAction::make('bulkCheckWebsites')
            ->label('Check websites')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->authorizeIndividualRecords('update')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $queued = 0;

                foreach ($records as $lead) {
                    if ($lead instanceof Lead && filled($lead->website)) {
                        CheckWebsite::forLead($lead);
                        $queued++;
                    }
                }

                Notification::make()
                    ->title("Queued {$queued} website ".str('check')->plural($queued))
                    ->body($queued < $records->count() ? ($records->count() - $queued).' had no website.' : null)
                    ->success()
                    ->send();
            });
    }

    public static function describe(Lead $lead): string
    {
        return implode(' · ', array_filter([$lead->name, $lead->domain, $lead->city]));
    }

    private static function ownerSelect(): Select
    {
        return Select::make('owner_id')
            ->label('Owner')
            ->options(fn (): array => User::query()->whereHas('roles')->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->placeholder('Unassigned');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function owner(array $data): ?User
    {
        return filled($data['owner_id'] ?? null) ? User::query()->find($data['owner_id']) : null;
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
