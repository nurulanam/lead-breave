<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('search.per_search_request_cap', 300);
        $this->migrator->add('search.monthly_request_cap', 3000);
        $this->migrator->add('search.max_tiles', 64);
        $this->migrator->add('search.places_price_per_1000', 35.0);
        $this->migrator->add('search.geocoding_price_per_1000', 5.0);
    }
};
