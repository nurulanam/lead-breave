<?php

namespace App\Filament\Resources\Searches\RelationManagers;

use App\Enums\LeadStatus;
use App\Enums\SearchStatus;
use App\Models\Lead;
use App\Models\Search;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The businesses a search found, in Google's ranking order. Read-only here;
 * leads are edited from the Leads screen.
 */
class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Businesses found';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Search $search */
        $search = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('lead_search.rank')
            ->poll(fn (): ?string => in_array($search->fresh()?->status, [SearchStatus::Pending, SearchStatus::Running], true) ? '5s' : null)
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('lead_search.rank', $direction))
                    ->color('gray')
                    ->width('1%'),
                TextColumn::make('name')
                    ->label('Business')
                    ->weight('medium')
                    ->description(fn (Lead $record): ?string => $record->category)
                    ->searchable(),
                TextColumn::make('website')
                    ->formatStateUsing(fn (Lead $record): ?string => $record->domain)
                    ->url(fn (Lead $record): ?string => $record->website, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('No website'),
                TextColumn::make('phone')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('rating')
                    ->numeric(1)
                    ->description(fn (Lead $record): string => number_format((int) $record->review_count).' reviews')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('city')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('was_new')
                    ->label('New')
                    ->boolean()
                    ->trueIcon('heroicon-m-sparkles')
                    ->falseIcon('heroicon-m-link')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Lead $record): string => $record->getAttribute('was_new') ? 'New lead from this search' : 'Already known from an earlier search')
                    ->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('website')
                    ->label('Website')
                    ->trueLabel('Has a website')
                    ->falseLabel('No website')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('website'),
                        false: fn (Builder $query) => $query->whereNull('website'),
                    ),
                SelectFilter::make('status')->options(LeadStatus::class),
            ])
            ->emptyStateHeading('No businesses yet')
            ->emptyStateDescription('They appear here as each cell is searched.');
    }
}
