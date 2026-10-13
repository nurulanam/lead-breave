<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Filament\Resources\Searches\SearchResource;
use App\Models\Lead;
use App\Models\Search;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SearchesRelationManager extends RelationManager
{
    protected static string $relationship = 'searches';

    protected static ?string $title = 'Found by';

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var Lead $ownerRecord */
        return Tab::make('Found by')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->badge($ownerRecord->searches()->count() ?: null);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('industry')
            ->defaultSort('lead_search.created_at', 'desc')
            ->recordUrl(fn (Search $record): string => SearchResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('industry')
                    ->label('Search')
                    ->formatStateUsing(fn (Search $record): string => SearchResource::getRecordTitle($record))
                    ->weight('medium'),
                TextColumn::make('rank')
                    ->label('Position')
                    ->prefix('#'),
                IconColumn::make('was_new')
                    ->label('First found here')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Run')
                    ->since(),
            ])
            ->emptyStateHeading('Not found by a search')
            ->emptyStateDescription('This lead was added by hand or imported.');
    }
}
