<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Services\Leads\LeadMatcher;
use App\Support\Countries;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Grid::make(1)
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Section::make('Contact')
                            ->icon(Heroicon::OutlinedPhone)
                            ->columns(2)
                            ->schema([
                                TextEntry::make('phone')
                                    ->url(fn (Lead $record): ?string => $record->phone_e164 ? 'tel:'.$record->phone_e164 : null)
                                    ->copyable()
                                    ->placeholder('No phone number'),
                                TextEntry::make('website')
                                    ->formatStateUsing(fn (Lead $record): ?string => $record->domain)
                                    ->url(fn (Lead $record): ?string => $record->website, shouldOpenInNewTab: true)
                                    ->color('primary')
                                    ->placeholder('No website'),
                                TextEntry::make('primaryEmail.email')
                                    ->label('Main email')
                                    ->copyable()
                                    ->placeholder('No email yet')
                                    ->helperText(fn (Lead $record): ?string => ($others = $record->emails()->count() - 1) > 0 ? "+ {$others} more in the Emails tab" : null),
                                TextEntry::make('address')
                                    ->state(fn (Lead $record): ?string => implode('<br>', array_map('e', array_filter([
                                        $record->street,
                                        implode(' ', array_filter([$record->city, $record->region, $record->postal_code])),
                                        $record->country ? Countries::name($record->country) : null,
                                    ]))) ?: null)
                                    ->html()
                                    ->placeholder('No address'),
                            ]),
                        Section::make('Google listing')
                            ->icon(Heroicon::OutlinedStar)
                            ->columns(3)
                            ->schema([
                                TextEntry::make('rating')
                                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 1).' ★')
                                    ->placeholder('No rating'),
                                TextEntry::make('review_count')
                                    ->label('Reviews')
                                    ->numeric()
                                    ->placeholder('—'),
                                TextEntry::make('category')
                                    ->placeholder('—'),
                            ]),
                        Section::make('Possible duplicates')
                            ->icon(Heroicon::OutlinedDocumentDuplicate)
                            ->description('Leads with the same website, phone, email, or name and city. Use "Merge duplicate" in the menu above to combine them.')
                            ->visible(fn (Lead $record): bool => LeadMatcher::possibleDuplicates($record, 1)->isNotEmpty())
                            ->schema([
                                View::make('filament.leads.duplicates')
                                    ->viewData(fn (Lead $record): array => [
                                        'duplicates' => LeadMatcher::possibleDuplicates($record),
                                        'url' => fn (Lead $lead): string => LeadResource::getUrl('view', ['record' => $lead]),
                                    ]),
                            ]),
                    ]),
                Grid::make(1)
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Section::make('Pipeline')
                            ->icon(Heroicon::OutlinedFunnel)
                            ->schema([
                                TextEntry::make('status')->badge(),
                                TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                                TextEntry::make('source')
                                    ->formatStateUsing(fn (Lead $record): string => match ($record->source->value) {
                                        'manual' => 'Added by hand',
                                        'csv' => 'CSV import',
                                        default => 'Google Maps search',
                                    }),
                                TextEntry::make('created_at')->label('Added')->since()->dateTimeTooltip(),
                            ]),
                        Section::make('Location')
                            ->icon(Heroicon::OutlinedMap)
                            ->visible(fn (Lead $record): bool => $record->lat !== null && $record->lng !== null)
                            ->schema([
                                View::make('filament.search.map')
                                    ->viewData(fn (Lead $record): array => [
                                        'data' => [
                                            'center' => ['lat' => $record->lat, 'lng' => $record->lng],
                                            'zoom' => 15,
                                            'marker' => true,
                                            'area' => null,
                                            'tiles' => [],
                                        ],
                                        'height' => 'h-52',
                                        'showLegend' => false,
                                        'mapKey' => 'lead-map-'.$record->getKey(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
