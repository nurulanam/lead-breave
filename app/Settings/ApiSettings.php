<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Third-party API keys. Stored encrypted and never sent to the browser.
 */
class ApiSettings extends Settings
{
    public ?string $google_places_key;

    public ?string $pagespeed_key;

    public ?string $anthropic_key;

    public static function group(): string
    {
        return 'api';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return self::keys();
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return ['google_places_key', 'pagespeed_key', 'anthropic_key'];
    }

    public function has(string $key): bool
    {
        return filled($this->{$key} ?? null);
    }
}
