<?php

namespace App\Filament\Resources\Searches\Tables;

use App\Enums\SearchStatus;
use App\Filament\Resources\Searches\SearchActions;
use App\Models\Search;
use App\Settings\SearchSettings;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SearchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->poll(fn (): ?string => Search::query()->whereIn('status', [SearchStatus::Pending, SearchStatus::Running])->exists() ? '5s' : null)
            ->columns([
                TextColumn::make('industry')
                    ->label('Search')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->description(fn (Search $record): string => $record->location)
                    ->weight('medium')
                    ->searchable(['industry', 'location', 'zip']),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                ViewColumn::make('progress')
                    ->view('filament.search.progress-column'),
                TextColumn::make('leads_created')
                    ->label('New leads')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('leads_linked')
                    ->label('Known')
                    ->numeric()
                    ->alignEnd()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('api_cost_units')
                    ->label('Requests')
                    ->numeric()
                    ->alignEnd()
                    ->description(fn (Search $record): string => '≈ $'.number_format(app(SearchSettings::class)->estimateCost($record->api_cost_units), 2))
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Started')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(SearchStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    SearchActions::rerun(),
                    SearchActions::stop(),
                    DeleteAction::make()->modalDescription('The search and its cells are deleted. Leads it found are kept.'),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-map')
            ->emptyStateHeading('No searches yet')
            ->emptyStateDescription('Start with a trade and a ZIP code, such as "plumber" in 10001.');
    }
}
