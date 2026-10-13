<?php

namespace App\Services\ApiKeys;

use App\Enums\ApiKey;
use App\Models\ApiCall;
use App\Services\Google\Geocoder;
use App\Services\Google\GoogleApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Checks that a key can reach every API LeadBreave needs, using the cheapest
 * call each API offers: Place Details with an ID-only field mask (free),
 * PageSpeed with a deliberately invalid URL (free, rejected after the key is
 * checked), one geocode (inside Google's free monthly allowance) and
 * Anthropic's model list (free).
 */
class ApiKeyTester
{
    /** A well-known place used only to prove the key can reach Places. */
    public const PROBE_PLACE_ID = 'ChIJN1t_tDeuEmsRUsoyG83frY4';

    public const PLACES_URL = 'https://places.googleapis.com/v1/places/';

    public const PAGESPEED_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public const ANTHROPIC_URL = 'https://api.anthropic.com/v1/models';

    /**
     * @return array<string, array{ok: bool, message: string}>
     */
    public function test(ApiKey $key, string $value): array
    {
        return match ($key) {
            ApiKey::Google => [
                'places' => $this->attempt('Places API (New)', $value, fn (): array => $this->places($value)),
                'geocoding' => $this->attempt('Geocoding API', $value, fn (): array => $this->geocoding($value)),
                'pagespeed' => $this->attempt('PageSpeed Insights API', $value, fn (): array => $this->pagespeed($value)),
            ],
            ApiKey::Anthropic => [
                'messages' => $this->attempt('Claude API', $value, fn (): array => $this->anthropic($value)),
            ],
        };
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function places(string $value): array
    {
        $response = $this->client()
            ->withHeaders(['X-Goog-Api-Key' => $value, 'X-Goog-FieldMask' => 'id'])
            ->get(self::PLACES_URL.self::PROBE_PLACE_ID);

        return $response->successful()
            ? $this->passed('Connected')
            : $this->googleFailure('Places API (New)', $response);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function geocoding(string $value): array
    {
        $startedAt = hrtime(true);
        $response = $this->client()->get(Geocoder::URL, ['address' => '10001', 'components' => 'country:US', 'key' => $value]);
        $status = (string) $response->json('status', 'UNKNOWN_ERROR');
        $billed = in_array($status, ['OK', 'ZERO_RESULTS'], true);

        ApiCall::record('google_geocoding', 'geocode/json (key test)', $response->status(), (int) round((hrtime(true) - $startedAt) / 1e6), $billed ? 1 : 0);

        if ($billed) {
            return $this->passed('Connected');
        }

        return $this->failed(GoogleApiException::fromResponse('Geocoding API', $response->status(), (string) $response->json('error_message', $status))->getMessage());
    }

    /**
     * Google checks the key before the URL, so "invalid URL" proves the key works.
     *
     * @return array{ok: bool, message: string}
     */
    private function pagespeed(string $value): array
    {
        $response = $this->client()->get(self::PAGESPEED_URL, ['url' => 'leadbreave-key-test', 'key' => $value]);
        $reason = (string) $response->json('error.errors.0.reason', '');
        $status = (string) $response->json('error.status', '');

        if ($response->successful() || ($response->status() === 400 && ($status === 'INVALID_ARGUMENT' || $reason === 'invalid') && ! str_contains(strtolower((string) $response->json('error.message')), 'key'))) {
            return $this->passed('Connected');
        }

        return $this->googleFailure('PageSpeed Insights API', $response);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function anthropic(string $value): array
    {
        $response = $this->client()
            ->withHeaders(['x-api-key' => $value, 'anthropic-version' => '2023-06-01'])
            ->get(self::ANTHROPIC_URL, ['limit' => 1]);

        return match (true) {
            $response->successful() => $this->passed('Connected'),
            $response->status() === 401 => $this->failed('Anthropic does not recognise this key. Check it was copied in full and has not been revoked.'),
            $response->status() === 403 => $this->failed('This key is not allowed to use the API. Check its workspace permissions in the Claude Console.'),
            $response->status() === 429 => $this->failed('Anthropic\'s rate limit was reached. Try again in a minute.'),
            default => $this->failed('Anthropic returned an error: '.$response->json('error.message', 'HTTP '.$response->status())),
        };
    }

    /**
     * Run one check, turning network errors into a result and keeping the key out of every message.
     *
     * @param  callable(): array{ok: bool, message: string}  $check
     * @return array{ok: bool, message: string}
     */
    private function attempt(string $api, string $value, callable $check): array
    {
        try {
            $result = $check();
        } catch (ConnectionException) {
            $result = $this->failed("Could not reach {$api}. Check this server's internet connection.");
        }

        return ['ok' => $result['ok'], 'message' => str_replace($value, '[redacted]', $result['message'])];
    }

    private function client(): PendingRequest
    {
        return Http::timeout(15)->connectTimeout(5);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function googleFailure(string $api, Response $response): array
    {
        return $this->failed(GoogleApiException::fromResponse($api, $response->status(), (string) $response->json('error.message', 'HTTP '.$response->status()))->getMessage());
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function passed(string $message): array
    {
        return ['ok' => true, 'message' => $message];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function failed(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
