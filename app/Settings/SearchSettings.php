<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Limits and prices for Google Maps searches. Caps are counted in Places
 * requests from the api_calls ledger.
 */
class SearchSettings extends Settings
{
    public int $per_search_request_cap;

    public int $monthly_request_cap;

    public int $max_tiles;

    /** USD per 1,000 Text Search requests, used for estimates only. */
    public float $places_price_per_1000;

    /** USD per 1,000 Geocoding requests, used for estimates only. */
    public float $geocoding_price_per_1000;

    public static function group(): string
    {
        return 'search';
    }

    public function estimateCost(int $placesRequests, int $geocodingRequests = 0): float
    {
        return round(
            $placesRequests * $this->places_price_per_1000 / 1000 + $geocodingRequests * $this->geocoding_price_per_1000 / 1000,
            2,
        );
    }
}
