<?php

namespace App\Services\Google;

use App\Models\ApiCall;
use App\Settings\ApiSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Geocoding API. It only accepts the key as a query parameter, so any message
 * that could contain the request URL is redacted before it leaves this class.
 */
class Geocoder
{
    public const URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(private ApiSettings $settings) {}

    /**
     * Resolve a ZIP code or place name. Results are cached for 30 days as plain
     * arrays (the cache refuses to unserialize objects), so repeat searches in
     * the same area cost nothing.
     *
     * @throws GoogleApiException
     */
    public function geocode(string $location, string $country = 'US', ?Model $related = null): GeocodeResult
    {
        $cacheKey = 'geocode:'.sha1(mb_strtolower(trim($location)).'|'.strtoupper($country));

        return GeocodeResult::fromArray(
            Cache::remember($cacheKey, now()->addDays(30), fn (): array => $this->fetch($location, $country, $related)->toArray()),
        );
    }

    private function fetch(string $location, string $country, ?Model $related): GeocodeResult
    {
        $key = $this->settings->google_api_key ?? throw GoogleApiException::missingKey();
        $startedAt = hrtime(true);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->retry(2, 500, throw: false)
                ->get(self::URL, ['address' => $location, 'components' => 'country:'.strtoupper($country), 'key' => $key]);
        } catch (ConnectionException $e) {
            ApiCall::record('google_geocoding', 'geocode/json', null, $this->elapsedMs($startedAt), 0, $related);

            throw GoogleApiException::unreachable('Could not reach Google Geocoding: '.str_replace($key, '[redacted]', $e->getMessage()));
        }

        $status = (string) $response->json('status', 'UNKNOWN_ERROR');
        $billed = in_array($status, ['OK', 'ZERO_RESULTS'], true);

        ApiCall::record('google_geocoding', 'geocode/json', $response->status(), $this->elapsedMs($startedAt), $billed ? 1 : 0, $related);

        if ($status === 'ZERO_RESULTS') {
            throw new GoogleApiException("Google could not find \"{$location}\". Try a ZIP code or \"City, State\".");
        }

        if ($status !== 'OK' || $response->failed()) {
            $message = str_replace($key, '[redacted]', (string) $response->json('error_message', $status));

            throw GoogleApiException::fromResponse(
                'Geocoding API',
                $response->status() === 200 ? null : $response->status(),
                $message,
                retryable: in_array($status, ['OVER_QUERY_LIMIT', 'UNKNOWN_ERROR'], true),
            );
        }

        return $this->toResult($response->json('results.0'));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function toResult(array $result): GeocodeResult
    {
        $components = [];

        foreach ($result['address_components'] ?? [] as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $components[$type] ??= in_array($type, ['administrative_area_level_1', 'country'], true)
                    ? $component['short_name']
                    : $component['long_name'];
            }
        }

        $viewport = $result['geometry']['viewport'] ?? null;
        $radius = 5000;

        if ($viewport !== null) {
            $northEast = new Coordinates((float) $viewport['northeast']['lat'], (float) $viewport['northeast']['lng']);
            $southWest = new Coordinates((float) $viewport['southwest']['lat'], (float) $viewport['southwest']['lng']);
            $radius = (int) min(25000, max(1000, round($northEast->distanceTo($southWest) / 2, -2)));
        }

        return new GeocodeResult(
            center: new Coordinates((float) $result['geometry']['location']['lat'], (float) $result['geometry']['location']['lng']),
            formattedAddress: (string) ($result['formatted_address'] ?? ''),
            postalCode: $components['postal_code'] ?? null,
            city: $components['locality'] ?? $components['postal_town'] ?? $components['sublocality'] ?? null,
            region: $components['administrative_area_level_1'] ?? null,
            country: $components['country'] ?? null,
            suggestedRadiusM: $radius,
        );
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1e6);
    }
}
