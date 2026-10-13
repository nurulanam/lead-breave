<?php

namespace App\Filament\Resources\Searches\Schemas;

use App\Models\Search;
use App\Services\Search\SearchMapData;
use App\Settings\SearchSettings;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class SearchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 5])
            ->components([
                Section::make('Progress')
                    ->columnSpan(['lg' => 3])
                    ->afterHeader(fn (Search $record) => view('filament.search.status-badge', ['status' => $record->status]))
                    ->schema([
                        View::make('filament.search.summary')
                            ->viewData(fn (Search $record): array => [
                                'search' => $record,
                                'cost' => app(SearchSettings::class)->estimateCost($record->api_cost_units),
                            ]),
                    ]),
                Section::make('Coverage')
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        View::make('filament.search.map')
                            ->viewData(fn (Search $record): array => [
                                'data' => SearchMapData::forSearch($record) ?? SearchMapData::default(),
                                'height' => 'h-64',
                            ]),
                    ]),
            ]);
    }
}
