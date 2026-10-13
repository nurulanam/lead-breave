<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\LeadActions;
use App\Models\Lead;
use App\Support\Countries;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['primaryEmail', 'socials', 'owner']))
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search name, website, city, phone or email')
            ->splitSearchTerms(false)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->columns([
                TextColumn::make('name')
                    ->label('Business')
                    ->weight('medium')
                    ->description(fn (Lead $record): string => implode(' · ', array_filter([$record->category, $record->city])))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search))
                    ->sortable(),
                TextColumn::make('website')
                    ->formatStateUsing(fn (Lead $record): ?string => $record->domain)
                    ->url(fn (Lead $record): ?string => $record->website, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('No website')
                    ->toggleable(),
                TextColumn::make('primaryEmail.email')
                    ->label('Email')
                    ->placeholder('No email')
                    ->copyable()
                    ->toggleable(),
                ViewColumn::make('socials')
                    ->label('Socials')
                    ->view('filament.leads.socials-column')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rating')
                    ->numeric(1)
                    ->description(fn (Lead $record): string => number_format((int) $record->review_count).' reviews')
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('fit_score')
                    ->label('Fit')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeadStatus::class)
                    ->multiple(),
                TernaryFilter::make('website')
                    ->label('Website')
                    ->trueLabel('Has a website')
                    ->falseLabel('No website')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('website'),
                        false: fn (Builder $query) => $query->whereNull('website'),
                    ),
                TernaryFilter::make('email')
                    ->label('Email')
                    ->trueLabel('Has an email')
                    ->falseLabel('No email')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('emails'),
                        false: fn (Builder $query) => $query->whereDoesntHave('emails'),
                    ),
                Filter::make('needs_info')
                    ->label('Needs info')
                    ->toggle()
                    ->query(fn (Builder $query) => Lead::whereNeedsInfo($query)),
                SelectFilter::make('owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->options(collect(LeadSource::cases())->mapWithKeys(fn (LeadSource $source): array => [$source->value => ucfirst($source->value)])),
                SelectFilter::make('category')
                    ->options(fn (): array => Lead::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category')->all())
                    ->searchable(),
                SelectFilter::make('country')
                    ->options(fn (): array => collect(Lead::query()->whereNotNull('country')->distinct()->pluck('country'))
                        ->mapWithKeys(fn (string $code): array => [$code => Countries::name($code)])
                        ->sort()
                        ->all())
                    ->searchable(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    LeadActions::bulkAssignOwner(),
                    LeadActions::bulkChangeStatus(),
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateHeading('No leads here')
            ->emptyStateDescription('Run a search, add a lead by hand, or import a CSV.');
    }

    /**
     * One box finds a lead by name, website, city, phone or any of its emails.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public static function search(Builder $query, string $search): Builder
    {
        $digits = preg_replace('/\D/', '', $search);

        return $query->where(fn (Builder $any) => $any
            ->where('name', 'like', "%{$search}%")
            ->orWhere('domain', 'like', "%{$search}%")
            ->orWhere('city', 'like', "%{$search}%")
            ->when(strlen((string) $digits) >= 4, fn (Builder $phone) => $phone->orWhere('phone_e164', 'like', "%{$digits}%"))
            ->orWhereHas('emails', fn (Builder $email) => $email->where('email', 'like', "%{$search}%")));
    }
}
