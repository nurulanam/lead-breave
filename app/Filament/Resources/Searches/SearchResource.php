<?php

namespace App\Filament\Resources\Searches;

use App\Filament\Resources\Searches\Pages\CreateSearch;
use App\Filament\Resources\Searches\Pages\ListSearches;
use App\Filament\Resources\Searches\Pages\ViewSearch;
use App\Filament\Resources\Searches\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\Searches\Schemas\SearchForm;
use App\Filament\Resources\Searches\Schemas\SearchInfolist;
use App\Filament\Resources\Searches\Tables\SearchesTable;
use App\Models\Search;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SearchResource extends Resource
{
    protected static ?string $model = Search::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Prospecting';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'industry';

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Search ? ucfirst($record->industry).' in '.$record->location : 'Search';
    }

    public static function form(Schema $schema): Schema
    {
        return SearchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SearchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SearchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LeadsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSearches::route('/'),
            'create' => CreateSearch::route('/create'),
            'view' => ViewSearch::route('/{record}'),
        ];
    }
}
