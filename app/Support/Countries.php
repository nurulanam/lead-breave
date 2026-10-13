<?php

namespace App\Support;

use libphonenumber\PhoneNumberUtil;
use Locale;

/**
 * Every country Google Maps covers, with English names. Built from
 * libphonenumber's region list and the intl extension, so no extra data
 * file is needed.
 */
class Countries
{
    /**
     * @return array<string, string> ISO 3166 code => name, sorted by name.
     */
    public static function options(): array
    {
        static $options = null;

        if ($options === null) {
            $options = [];

            foreach (PhoneNumberUtil::getInstance()->getSupportedRegions() as $code) {
                $options[$code] = self::name($code);
            }

            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $options;
    }

    public static function name(?string $code): string
    {
        $code = strtoupper((string) $code);
        $name = $code !== '' ? Locale::getDisplayRegion('-'.$code, 'en') : '';

        return $name !== '' && $name !== $code ? $name : $code;
    }

    public static function exists(?string $code): bool
    {
        return array_key_exists(strtoupper((string) $code), self::options());
    }
}
