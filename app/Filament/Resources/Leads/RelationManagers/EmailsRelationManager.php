<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\DataSource;
use App\Filament\Resources\Leads\RelationManagers\Concerns\RefreshesWhenLeadUpdates;
use App\Models\Lead;
use App\Models\LeadEmail;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Every address found for the lead. Addresses added here are marked manual,
 * so a later website crawl never overwrites or removes them.
 */
class EmailsRelationManager extends RelationManager
{
    use RefreshesWhenLeadUpdates;

    protected static string $relationship = 'emails';

    /**
     * Editable on the lead's View page, which is where people work on a lead.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var Lead $ownerRecord */
        return Tab::make('Emails')
            ->icon(Heroicon::OutlinedEnvelope)
            ->badge($ownerRecord->emails()->count() ?: null);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('lead_id', $this->getOwnerRecord()->getKey()))
                ->validationMessages(['unique' => 'This lead already has that address.']),
            Toggle::make('is_primary')
                ->label('Use as the main address')
                ->default(fn (): bool => $this->lead()->emails()->doesntExist()),
        ]);
    }

    private function lead(): Lead
    {
        $lead = $this->getOwnerRecord();

        return $lead instanceof Lead ? $lead : abort(404);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->defaultSort('is_primary', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->weight('medium')
                    ->copyable()
                    ->description(fn (LeadEmail $record): ?string => $record->is_role ? 'Shared inbox' : null),
                IconColumn::make('is_primary')
                    ->label('Main')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('confidence')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->color(fn (int $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 55 => 'warning',
                        default => 'gray',
                    })
                    ->tooltip('How likely this address reaches a real person at the business'),
                TextColumn::make('source')
                    ->formatStateUsing(fn (DataSource $state): string => match ($state) {
                        DataSource::Crawl => 'Found on website',
                        DataSource::Manual => 'Added by hand',
                        DataSource::Csv => 'CSV import',
                    })
                    ->color('gray'),
                TextColumn::make('found_on_url')
                    ->label('Found on')
                    ->url(fn (LeadEmail $record): ?string => $record->found_on_url, shouldOpenInNewTab: true)
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add email')
                    ->icon(Heroicon::OutlinedPlus)
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'source' => DataSource::Manual, 'confidence' => 100])
                    ->after(function (LeadEmail $record): void {
                        if ($record->is_primary) {
                            $record->makePrimary();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('makePrimary')
                    ->label('Make main')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('gray')
                    ->visible(fn (LeadEmail $record): bool => ! $record->is_primary)
                    ->action(fn (LeadEmail $record) => $record->makePrimary()),
                DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading('No email addresses yet')
            ->emptyStateDescription('Add one by hand, or they will be found when the website is checked.');
    }
}
