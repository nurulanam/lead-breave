<?php

namespace App\Support;

use DateTimeZone;

/**
 * Best-effort IANA timezone for a business, without an API call. US states
 * use a state map; elsewhere the country's zone nearest to the business wins.
 */
class Timezones
{
    /** @var array<string, string> */
    private const US_STATES = [
        'AL' => 'America/Chicago', 'AK' => 'America/Anchorage', 'AZ' => 'America/Phoenix', 'AR' => 'America/Chicago',
        'CA' => 'America/Los_Angeles', 'CO' => 'America/Denver', 'CT' => 'America/New_York', 'DE' => 'America/New_York',
        'DC' => 'America/New_York', 'FL' => 'America/New_York', 'GA' => 'America/New_York', 'HI' => 'Pacific/Honolulu',
        'ID' => 'America/Boise', 'IL' => 'America/Chicago', 'IN' => 'America/Indiana/Indianapolis', 'IA' => 'America/Chicago',
        'KS' => 'America/Chicago', 'KY' => 'America/New_York', 'LA' => 'America/Chicago', 'ME' => 'America/New_York',
        'MD' => 'America/New_York', 'MA' => 'America/New_York', 'MI' => 'America/Detroit', 'MN' => 'America/Chicago',
        'MS' => 'America/Chicago', 'MO' => 'America/Chicago', 'MT' => 'America/Denver', 'NE' => 'America/Chicago',
        'NV' => 'America/Los_Angeles', 'NH' => 'America/New_York', 'NJ' => 'America/New_York', 'NM' => 'America/Denver',
        'NY' => 'America/New_York', 'NC' => 'America/New_York', 'ND' => 'America/Chicago', 'OH' => 'America/New_York',
        'OK' => 'America/Chicago', 'OR' => 'America/Los_Angeles', 'PA' => 'America/New_York', 'RI' => 'America/New_York',
        'SC' => 'America/New_York', 'SD' => 'America/Chicago', 'TN' => 'America/Chicago', 'TX' => 'America/Chicago',
        'UT' => 'America/Denver', 'VT' => 'America/New_York', 'VA' => 'America/New_York', 'WA' => 'America/Los_Angeles',
        'WV' => 'America/New_York', 'WI' => 'America/Chicago', 'WY' => 'America/Denver', 'PR' => 'America/Puerto_Rico',
    ];

    public static function guess(?string $country, ?string $region = null, ?float $lat = null, ?float $lng = null): ?string
    {
        $country = strtoupper((string) $country);

        if ($country === 'US' && $region !== null && isset(self::US_STATES[strtoupper($region)])) {
            return self::US_STATES[strtoupper($region)];
        }

        $zones = $country !== '' ? DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country) : [];

        if (count($zones) <= 1 || $lat === null || $lng === null) {
            return $zones[0] ?? null;
        }

        $nearest = null;
        $nearestDistance = INF;

        foreach ($zones as $zone) {
            $location = (new DateTimeZone($zone))->getLocation();

            if ($location === false) {
                continue;
            }

            $distance = ($location['latitude'] - $lat) ** 2 + (($location['longitude'] - $lng) * cos(deg2rad($lat))) ** 2;

            if ($distance < $nearestDistance) {
                $nearest = $zone;
                $nearestDistance = $distance;
            }
        }

        return $nearest ?? $zones[0];
    }
}
