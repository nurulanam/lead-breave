<?php

namespace App\Support;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class Phone
{
    /**
     * E.164 form ("+12125550123") of the first number that parses, or null.
     * The international form is tried first because it carries its own
     * country code; the national form falls back to the given country.
     */
    public static function toE164(?string $international, ?string $national = null, ?string $country = 'US'): ?string
    {
        foreach ([$international, $national] as $number) {
            if (blank($number)) {
                continue;
            }

            try {
                $phone = (new PhoneNumber($number, $country))->lenient();

                if ($phone->isValid()) {
                    return $phone->formatE164();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
