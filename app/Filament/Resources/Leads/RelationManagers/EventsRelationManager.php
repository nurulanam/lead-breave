<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Models\LeadEvent;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The lead's timeline, newest first.
 */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Activity';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedClock;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50])
            ->columns([
                TextColumn::make('type')
                    ->label('What happened')
                    ->formatStateUsing(fn (LeadEvent $record): string => $record->summary())
                    ->icon(fn (LeadEvent $record): string => $record->type->icon())
                    ->iconColor('primary')
                    ->description(fn (LeadEvent $record): string => $record->type->label())
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('LeadBreave'),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip(),
            ]);
    }
}
