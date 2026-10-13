<?php

namespace App\Services\Google;

use App\Models\ApiCall;
use App\Settings\ApiSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Places API (New) Text Search. The key travels in a header, never the URL.
 */
class PlacesClient
{
    public const URL = 'https://places.googleapis.com/v1/places:searchText';

    public const PAGE_SIZE = 20;

    /**
     * Only the fields we store. Each extra field can move the request to a pricier SKU.
     */
    public const FIELD_MASK = 'places.id,places.displayName,places.addressComponents,places.location,'
        .'places.nationalPhoneNumber,places.internationalPhoneNumber,places.websiteUri,places.rating,'
        .'places.userRatingCount,places.googleMapsUri,places.primaryTypeDisplayName,places.businessStatus,'
        .'nextPageToken';

    public function __construct(private ApiSettings $settings) {}

    /**
     * Fetch one page of results inside an area. Every page of the same search must
     * send the same query and area, plus the previous page's token.
     *
     * @throws GoogleApiException
     */
    public function searchText(string $query, Rectangle $area, ?string $pageToken = null, ?Model $related = null, ?string $regionCode = null): PlacesPage
    {
        $key = $this->settings->google_api_key ?? throw GoogleApiException::missingKey();

        $body = array_filter([
            'textQuery' => $query,
            'pageSize' => self::PAGE_SIZE,
            'locationRestriction' => ['rectangle' => $area->toApi()],
            'regionCode' => $regionCode !== null ? strtolower($regionCode) : null,
            'pageToken' => $pageToken,
        ]);

        $startedAt = hrtime(true);

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => self::FIELD_MASK])
                ->retry(3, fn (int $attempt): int => $attempt * 1000, $this->isRetryable(...), throw: false)
                ->post(self::URL, $body);
        } catch (ConnectionException $e) {
            ApiCall::record('google_places', 'places:searchText', null, $this->elapsedMs($startedAt), 0, $related);

            throw GoogleApiException::unreachable('Could not reach Google Places: '.$e->getMessage());
        }

        ApiCall::record('google_places', 'places:searchText', $response->status(), $this->elapsedMs($startedAt), $response->successful() ? 1 : 0, $related);

        if ($response->failed()) {
            throw GoogleApiException::fromResponse('Places API (New)', $response->status(), (string) $response->json('error.message', $response->body()));
        }

        return new PlacesPage(
            places: array_map(Place::fromApi(...), $response->json('places', [])),
            nextPageToken: $response->json('nextPageToken'),
        );
    }

    private function isRetryable(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError()));
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1e6);
    }
}
