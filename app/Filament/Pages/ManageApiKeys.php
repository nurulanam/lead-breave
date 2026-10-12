<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Settings\ApiSettings;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Write-only form: stored keys are never loaded into the form state, so they
 * never reach the browser. A blank field keeps the current key.
 */
class ManageApiKeys extends SettingsPage
{
    protected static string $settings = ApiSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'API keys';

    protected static ?string $title = 'API keys';

    /** @var array<string, string> */
    private const LABELS = [
        'google_api_key' => 'Google API key',
        'anthropic_key' => 'Anthropic (Claude) API key',
    ];

    /** @var array<string, string> */
    private const HINTS = [
        'google_api_key' => 'One key for lead search, geocoding and speed tests. In Google Cloud, enable Places API (New), Geocoding API and PageSpeed Insights API, then restrict the key to those three APIs.',
        'anthropic_key' => 'Optional. Used by "Draft with AI" on the audit screen.',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function canEdit(): bool
    {
        return static::canAccess();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $current = app(ApiSettings::class);
        $values = [];

        foreach (ApiSettings::keys() as $key) {
            $values[$key] = match (true) {
                (bool) ($data["clear_{$key}"] ?? false) => null,
                filled($data[$key] ?? null) => trim($data[$key]),
                default => $current->{$key},
            };
        }

        return $values;
    }

    /**
     * Clear the submitted secrets from the component state.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(ApiSettings::class);

        return $schema
            ->columns(1)
            ->components(array_map(
                fn (string $key) => Fieldset::make(self::LABELS[$key])
                    ->columns(1)
                    ->schema([
                        TextInput::make($key)
                            ->hiddenLabel()
                            ->password()
                            ->autocomplete('new-password')
                            ->maxLength(255)
                            ->placeholder($settings->has($key) ? 'Saved. Leave blank to keep it.' : 'Not set')
                            ->helperText(self::HINTS[$key]),
                        Checkbox::make("clear_{$key}")
                            ->label('Remove the saved key')
                            ->visible($settings->has($key)),
                    ]),
                ApiSettings::keys(),
            ));
    }
}
