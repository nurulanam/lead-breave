<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('api.google_places_key', null);
        $this->migrator->addEncrypted('api.pagespeed_key', null);
        $this->migrator->addEncrypted('api.anthropic_key', null);
    }
};
