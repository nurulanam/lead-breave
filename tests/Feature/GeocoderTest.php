<?php

use App\Enums\SearchStatus;
use App\Jobs\PlanSearch;
use App\Models\ApiCall;
use App\Models\Search;
use App\Services\Google\Geocoder;
use App\Services\Google\GoogleApiException;
use App\Services\Google\PlacesClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useGoogleKey('secret-key-123');
    Http::preventStrayRequests();
});

/**
 * @return array<string, mixed>
 */
function geocodeResponse(): array
{
    return [
        'status' => 'OK',
        'results' => [[
            'formatted_address' => 'New York, NY 10001, USA',
            'geometry' => [
                'location' => ['lat' => 40.7506, 'lng' => -73.9972],
                'viewport' => [
                    'northeast' => ['lat' => 40.7601, 'lng' => -73.9851],
                    'southwest' => ['lat' => 40.7411, 'lng' => -74.0094],
                ],
            ],
            'address_components' => [
                ['long_name' => '10001', 'short_name' => '10001', 'types' => ['postal_code']],
                ['long_name' => 'New York', 'short_name' => 'New York', 'types' => ['locality', 'political']],
                ['long_name' => 'New York', 'short_name' => 'NY', 'types' => ['administrative_area_level_1', 'political']],
                ['long_name' => 'United States', 'short_name' => 'US', 'types' => ['country', 'political']],
            ],
        ]],
    ];
}

it('resolves a ZIP code to a centre and a suggested radius', function () {
    Http::fake([Geocoder::URL.'*' => Http::response(geocodeResponse())]);

    $result = app(Geocoder::class)->geocode('10001');

    expect($result->center->lat)->toBe(40.7506)
        ->and($result->postalCode)->toBe('10001')
        ->and($result->city)->toBe('New York')
        ->and($result->region)->toBe('NY')
        ->and($result->suggestedRadiusM)->toBe(1500)
        ->and(ApiCall::forProvider('google_geocoding')->sole()->cost_units)->toBe(1);

    Http::assertSent(fn (Request $request) => $request['address'] === '10001'
        && $request['components'] === 'country:US'
        && $request['key'] === 'secret-key-123');
});

it('serves repeat lookups from the cache', function () {
    config(['cache.default' => 'database']);
    Http::fake([Geocoder::URL.'*' => Http::response(geocodeResponse())]);

    app(Geocoder::class)->geocode('10001');
    $cached = app(Geocoder::class)->geocode(' 10001 ');

    expect($cached->center->lat)->toBe(40.7506)
        ->and($cached->postalCode)->toBe('10001');

    Http::assertSentCount(1);
    expect(ApiCall::count())->toBe(1);
});

it('explains a location Google cannot find, without caching the miss', function () {
    Http::fake([Geocoder::URL.'*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []])]);

    expect(fn () => app(Geocoder::class)->geocode('Nowhereville'))
        ->toThrow(GoogleApiException::class, 'Google could not find "Nowhereville"');
    expect(fn () => app(Geocoder::class)->geocode('Nowhereville'))->toThrow(GoogleApiException::class);

    Http::assertSentCount(2);
});

it('never puts the key in an error message', function () {
    Http::fake([Geocoder::URL.'*' => Http::response([
        'status' => 'REQUEST_DENIED',
        'error_message' => 'Request for key=secret-key-123 was denied.',
    ])]);

    try {
        app(Geocoder::class)->geocode('10001');
    } catch (GoogleApiException $e) {
        expect($e->getMessage())->not->toContain('secret-key-123')->toContain('[redacted]');

        return;
    }

    $this->fail('Expected a GoogleApiException.');
});

it('locates a search before planning its cells', function () {
    Http::fake([
        Geocoder::URL.'*' => Http::response(geocodeResponse()),
        PlacesClient::URL => Http::response(placesPage(1, 2)),
    ]);

    $search = Search::factory()->create(['center_lat' => null, 'center_lng' => null, 'zip' => null, 'location' => '10001', 'radius_m' => 1000]);

    PlanSearch::dispatchSync($search->id);

    expect($search->fresh())
        ->center_lat->toBe(40.7506)
        ->zip->toBe('10001')
        ->status->toBe(SearchStatus::Completed);
});

it('retries a temporary Geocoding error', function () {
    Http::fake([Geocoder::URL.'*' => Http::response(['status' => 'OVER_QUERY_LIMIT', 'error_message' => 'Try later.'])]);

    try {
        app(Geocoder::class)->geocode('10001');
    } catch (GoogleApiException $e) {
        expect($e->isRetryable())->toBeTrue();

        return;
    }

    $this->fail('Expected a GoogleApiException.');
});

it('fails a search whose location cannot be found', function () {
    Http::fake([Geocoder::URL.'*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []])]);

    $search = Search::factory()->create(['center_lat' => null, 'center_lng' => null, 'zip' => null, 'location' => 'Nowhereville']);

    PlanSearch::dispatchSync($search->id);

    expect($search->fresh())
        ->status->toBe(SearchStatus::Failed)
        ->stop_reason->toContain('could not find');
});
