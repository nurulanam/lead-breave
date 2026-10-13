<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\Search\CostGuard;
use App\Settings\SearchSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSearchSettings extends SettingsPage
{
    protected static string $settings = SearchSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Search';

    protected static ?string $title = 'Search settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function canEdit(): bool
    {
        return static::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        $used = app(CostGuard::class)->placesRequestsThisMonth();

        return $schema
            ->components([
                Section::make('Spending limits')
                    ->description('A search stops cleanly when it reaches either limit. Counted in Google Places requests.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('per_search_request_cap')
                            ->label('Requests per search')
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('monthly_request_cap')
                            ->label('Requests per month')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->helperText(number_format($used).' used so far this month.'),
                        TextInput::make('max_tiles')
                            ->label('Most cells per search')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(400)
                            ->required()
                            ->helperText('Large areas use bigger cells to stay under this number.'),
                    ]),
                Section::make('Prices for estimates')
                    ->description('Used only to show estimated costs. Check Google Cloud billing for your actual rates and free credit.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('places_price_per_1000')
                            ->label('Places Text Search')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->suffix('per 1,000')
                            ->required(),
                        TextInput::make('geocoding_price_per_1000')
                            ->label('Geocoding')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->suffix('per 1,000')
                            ->required(),
                    ]),
            ]);
    }
}
