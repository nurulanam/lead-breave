<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $company_name;

    public ?string $sender_name;

    public ?string $sender_email;

    /** Required in every outreach footer (CAN-SPAM). */
    public ?string $postal_address;

    public ?string $booking_url;

    public string $timezone;

    public static function group(): string
    {
        return 'general';
    }
}
