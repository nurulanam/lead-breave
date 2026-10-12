<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.company_name', 'LeadBreave');
        $this->migrator->add('general.sender_name', null);
        $this->migrator->add('general.sender_email', null);
        $this->migrator->add('general.postal_address', null);
        $this->migrator->add('general.booking_url', null);
        $this->migrator->add('general.timezone', 'America/New_York');
    }
};
