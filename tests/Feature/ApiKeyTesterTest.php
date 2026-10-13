<?php

use App\Enums\ApiKey;
use App\Models\ApiCall;
use App\Services\ApiKeys\ApiKeyTester;
use App\Services\Google\Geocoder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

it('passes a Google key that reaches all three APIs, using the cheapest calls', function () {
    Http::fake([
        ApiKeyTester::PLACES_URL.'*' => Http::response(['id' => ApiKeyTester::PROBE_PLACE_ID]),
        Geocoder::URL.'*' => Http::response(['status' => 'OK', 'results' => []]),
        ApiKeyTester::PAGESPEED_URL.'*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Invalid value for url']], 400),
    ]);

    $results = app(ApiKeyTester::class)->test(ApiKey::Google, 'google-key');

    expect(array_column($results, 'ok'))->toBe([true, true, true])
        ->and(ApiCall::forProvider('google_geocoding')->sole()->cost_units)->toBe(1);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), ApiKeyTester::PLACES_URL)
        && $request->hasHeader('X-Goog-FieldMask', 'id')
        && $request->hasHeader('X-Goog-Api-Key', 'google-key'));
});

it('explains a Google API that is not enabled on the key', function () {
    Http::fake([
        ApiKeyTester::PLACES_URL.'*' => Http::response(['id' => ApiKeyTester::PROBE_PLACE_ID]),
        Geocoder::URL.'*' => Http::response(['status' => 'OK', 'results' => []]),
        ApiKeyTester::PAGESPEED_URL.'*' => Http::response(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'PageSpeed Insights API has not been used in project 123 before or it is disabled.']], 403),
    ]);

    $pagespeed = app(ApiKeyTester::class)->test(ApiKey::Google, 'google-key')['pagespeed'];

    expect($pagespeed['ok'])->toBeFalse()
        ->and($pagespeed['message'])->toContain('Enable Places API (New), Geocoding API and PageSpeed Insights API');
});

it('never repeats the key in a failure message', function () {
    Http::fake([
        ApiKeyTester::PLACES_URL.'*' => Http::response(['error' => ['message' => 'Bad key google-key-123']], 400),
        Geocoder::URL.'*' => Http::response(['status' => 'REQUEST_DENIED', 'error_message' => 'Denied for google-key-123']),
        ApiKeyTester::PAGESPEED_URL.'*' => Http::failedConnection(),
    ]);

    $results = app(ApiKeyTester::class)->test(ApiKey::Google, 'google-key-123');

    foreach ($results as $result) {
        expect($result['ok'])->toBeFalse()
            ->and($result['message'])->not->toContain('google-key-123');
    }

    expect($results['pagespeed']['message'])->toContain('Could not reach PageSpeed Insights API');
});

it('checks an Anthropic key against the free model list', function (int $status, bool $ok, string $message) {
    Http::fake([ApiKeyTester::ANTHROPIC_URL.'*' => Http::response(['data' => []], $status)]);

    $result = app(ApiKeyTester::class)->test(ApiKey::Anthropic, 'sk-ant-test')['messages'];

    expect($result)->toBe(['ok' => $ok, 'message' => $message]);

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'sk-ant-test')
        && $request->hasHeader('anthropic-version'));
})->with([
    'valid key' => [200, true, 'Connected'],
    'unknown key' => [401, false, 'Anthropic does not recognise this key. Check it was copied in full and has not been revoked.'],
]);
