<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * One Google Cloud key now covers Places API (New), Geocoding and PageSpeed
 * Insights. The Places key is kept; a saved PageSpeed key is used only when
 * no Places key was set.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $pagespeedKey = null;

        $this->migrator->updateEncrypted('api.pagespeed_key', function (?string $value) use (&$pagespeedKey): ?string {
            $pagespeedKey = $value;

            return $value;
        });

        $this->migrator->rename('api.google_places_key', 'api.google_api_key');
        $this->migrator->updateEncrypted('api.google_api_key', fn (?string $value): ?string => filled($value) ? $value : $pagespeedKey);
        $this->migrator->delete('api.pagespeed_key');
    }
};
