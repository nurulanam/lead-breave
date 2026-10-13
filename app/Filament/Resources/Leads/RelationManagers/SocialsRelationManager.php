<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\DataSource;
use App\Enums\SocialPlatform;
use App\Models\Lead;
use App\Models\LeadSocial;
use App\Support\Domain;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SocialsRelationManager extends RelationManager
{
    protected static string $relationship = 'socials';

    protected static ?string $title = 'Social links';

    /** @var array<string, SocialPlatform> Host => platform, for guessing from a pasted link. */
    private const HOSTS = [
        'facebook.com' => SocialPlatform::Facebook, 'fb.com' => SocialPlatform::Facebook,
        'instagram.com' => SocialPlatform::Instagram, 'linkedin.com' => SocialPlatform::LinkedIn,
        'x.com' => SocialPlatform::X, 'twitter.com' => SocialPlatform::X, 'tiktok.com' => SocialPlatform::TikTok,
        'youtube.com' => SocialPlatform::YouTube, 'youtu.be' => SocialPlatform::YouTube,
        'pinterest.com' => SocialPlatform::Pinterest, 'yelp.com' => SocialPlatform::Yelp,
        'nextdoor.com' => SocialPlatform::Nextdoor, 'business.google.com' => SocialPlatform::GoogleBusiness,
    ];

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
        return Tab::make('Social links')
            ->icon(Heroicon::OutlinedShare)
            ->badge($ownerRecord->socials()->count() ?: null);
    }

    public static function platformFor(?string $url): ?SocialPlatform
    {
        $domain = Domain::fromUrl($url);

        foreach (self::HOSTS as $host => $platform) {
            if ($domain === $host || str_ends_with((string) $domain, '.'.$host)) {
                return $platform;
            }
        }

        return null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('url')
                ->label('Link')
                ->url()
                ->required()
                ->maxLength(512)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($platform = self::platformFor($state)) {
                        $set('platform', $platform->value);
                    }
                }),
            Select::make('platform')
                ->options(SocialPlatform::class)
                ->required()
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                TextColumn::make('platform')
                    ->formatStateUsing(fn (SocialPlatform $state): string => $state->label())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('url')
                    ->label('Link')
                    ->url(fn (LeadSocial $record): string => $record->url, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->limit(60),
                TextColumn::make('source')
                    ->formatStateUsing(fn (DataSource $state): string => $state === DataSource::Manual ? 'Added by hand' : ($state === DataSource::Csv ? 'CSV import' : 'Found on website'))
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add link')
                    ->icon(Heroicon::OutlinedPlus)
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'source' => DataSource::Manual]),
            ])
            ->recordActions([
                DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading('No social links yet')
            ->emptyStateDescription('Paste a Facebook, Instagram, LinkedIn or YouTube link, or they will be found when the website is checked.');
    }
}
