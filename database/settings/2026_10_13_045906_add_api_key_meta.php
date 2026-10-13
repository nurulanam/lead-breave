<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Who changed each key and when, and the result of its last connection
     * test. Never holds the keys themselves.
     */
    public function up(): void
    {
        $this->migrator->add('api.key_meta', '{}');
    }
};
