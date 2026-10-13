<?php

namespace App\Filament\Resources\Searches\Schemas;

use App\Enums\TilingMode;
use App\Services\Google\Coordinates;
use App\Services\Google\Geocoder;
use App\Services\Google\GoogleApiException;
use App\Services\Search\CostGuard;
use App\Services\Search\CoverageEstimate;
use App\Services\Search\SearchMapData;
use App\Settings\GeneralSettings;
use App\Settings\SearchSettings;
use App\Support\Countries;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class SearchForm
{
    /**
     * Trades people search for most often.
     *
     * @var list<string>
     */
    public const SUGGESTED_INDUSTRIES = [
        'plumber', 'electrician', 'roofer', 'hvac contractor', 'landscaper', 'dentist', 'chiropractor',
        'auto repair', 'law firm', 'real estate agent', 'restaurant', 'hair salon', 'cleaning service',
        'pest control', 'painter', 'general contractor', 'veterinarian', 'gym', 'accountant', 'florist',
    ];

    /** @var array<int, string> */
    public const RADII = [
        1000 => '1 km (0.6 mi)',
        2000 => '2 km (1.2 mi)',
        5000 => '5 km (3.1 mi)',
        10000 => '10 km (6.2 mi)',
        15000 => '15 km (9.3 mi)',
        25000 => '25 km (15.5 mi)',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 5])
            ->components([
                Section::make('What and where')
                    ->description('Google Maps is searched for this trade inside the area.')
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        TextInput::make('industry')
                            ->label('Business type')
                            ->placeholder('plumber')
                            ->required()
                            ->maxLength(100)
                            ->datalist(self::SUGGESTED_INDUSTRIES)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Component $livewire) => self::pushPreview($get, $livewire))
                            ->helperText('Use the words a customer would type into Google Maps.'),
                        Select::make('country')
                            ->label('Country')
                            ->options(Countries::options())
                            ->default(fn (): string => app(GeneralSettings::class)->default_country)
                            ->searchable()
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set, Component $livewire) => self::relocate($get, $set, $livewire)),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextInput::make('city')
                                    ->label('City or area')
                                    ->placeholder('Austin')
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                    ->maxLength(120)
                                    ->requiredWithout('zip')
                                    ->validationMessages(['required_without' => 'Enter a city or a ZIP / postal code.'])
                                    ->live(debounce: 700)
                                    ->afterStateUpdated(fn (Get $get, Set $set, Component $livewire) => self::relocate($get, $set, $livewire)),
                                TextInput::make('zip')
                                    ->label('ZIP / postal code')
                                    ->placeholder('78701')
                                    ->prefixIcon(Heroicon::OutlinedMapPin)
                                    ->maxLength(20)
                                    ->requiredWithout('city')
                                    ->validationMessages(['required_without' => 'Enter a city or a ZIP / postal code.'])
                                    ->live(debounce: 700)
                                    ->afterStateUpdated(fn (Get $get, Set $set, Component $livewire) => self::relocate($get, $set, $livewire)),
                            ]),
                        Select::make('radius_m')
                            ->label('Search radius')
                            ->options(self::RADII)
                            ->default(5000)
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Component $livewire) => self::pushPreview($get, $livewire)),
                        ToggleButtons::make('tiling')
                            ->label('Coverage')
                            ->options(TilingMode::class)
                            ->default(TilingMode::Grid)
                            ->inline()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Component $livewire) => self::pushPreview($get, $livewire)),
                        Hidden::make('center_lat'),
                        Hidden::make('center_lng'),
                        Hidden::make('location_label'),
                        Hidden::make('location_error')->dehydrated(false),
                    ]),
                Section::make('Coverage preview')
                    ->description('The search area and its cells. Crowded cells split into quarters automatically.')
                    ->columnSpan(['lg' => 3])
                    ->schema([
                        View::make('filament.search.coverage-preview')
                            ->viewData(fn (Get $get): array => self::previewData($get)),
                    ]),
            ]);
    }

    /**
     * Re-locate after the country, city or postal code changes, then update the map.
     */
    private static function relocate(Get $get, Set $set, Component $livewire): void
    {
        self::locate((string) $get('city'), (string) $get('zip'), (string) $get('country'), $set);
        self::pushPreview($get, $livewire);
    }

    /**
     * Find the city and/or postal code inside the chosen country. Lookups are
     * cached, so retyping a place costs nothing, and partial input is skipped.
     */
    private static function locate(string $city, string $postalCode, string $country, Set $set): void
    {
        $city = trim($city);
        $postalCode = trim($postalCode);
        $set('center_lat', null);
        $set('center_lng', null);
        $set('location_label', null);
        $set('location_error', null);

        if (! self::isWorthLookingUp($city, $postalCode, $country)) {
            return;
        }

        try {
            $place = app(Geocoder::class)->geocode($city, $postalCode, $country);
        } catch (GoogleApiException $e) {
            $set('location_error', $e->getMessage());

            return;
        }

        $set('center_lat', $place->center->lat);
        $set('center_lng', $place->center->lng);
        $set('location_label', $place->formattedAddress);
    }

    /**
     * Skip half-typed input: a city needs three letters, and a US ZIP all five digits.
     */
    private static function isWorthLookingUp(string $city, string $postalCode, string $country): bool
    {
        if (! Countries::exists($country)) {
            return false;
        }

        $postalReady = mb_strlen($postalCode) >= 3
            && ! (strtoupper($country) === 'US' && ctype_digit($postalCode) && strlen($postalCode) !== 5);

        return mb_strlen($city) >= 3 || $postalReady;
    }

    /**
     * Frame the chosen country before a city or postal code is typed. Falls
     * back to the default view when the country cannot be looked up.
     *
     * @return array<string, mixed>
     */
    private static function countryView(string $country): array
    {
        if (! Countries::exists($country)) {
            return SearchMapData::default();
        }

        try {
            return SearchMapData::forPlace(app(Geocoder::class)->geocode(null, null, $country));
        } catch (GoogleApiException) {
            return SearchMapData::default();
        }
    }

    /**
     * Send the current area to the map without re-rendering it.
     */
    private static function pushPreview(Get $get, Component $livewire): void
    {
        $livewire->dispatch('search-map-updated', data: self::previewData($get)['map']);
    }

    /**
     * @return array{map: array<string, mixed>, estimate: CoverageEstimate|null, label: string|null, error: string|null, usedThisMonth: int, monthlyCap: int}
     */
    private static function previewData(Get $get): array
    {
        $lat = $get('center_lat');
        $lng = $get('center_lng');
        $estimate = null;
        $map = self::countryView((string) $get('country'));

        if (is_numeric($lat) && is_numeric($lng)) {
            $center = new Coordinates((float) $lat, (float) $lng);
            $radius = (int) ($get('radius_m') ?: 5000);
            $tiling = $get('tiling');
            $tiling = $tiling instanceof TilingMode ? $tiling : (TilingMode::tryFrom((string) $tiling) ?? TilingMode::Grid);

            $estimate = CoverageEstimate::for($center, $radius, $tiling, (string) $get('industry'));
            $map = SearchMapData::forPlan($center, $radius, $estimate->tiles);
        }

        return [
            'map' => $map,
            'estimate' => $estimate,
            'label' => $get('location_label'),
            'error' => $get('location_error'),
            'usedThisMonth' => app(CostGuard::class)->placesRequestsThisMonth(),
            'monthlyCap' => app(SearchSettings::class)->monthly_request_cap,
        ];
    }
}
