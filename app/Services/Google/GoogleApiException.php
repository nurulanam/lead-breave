<?php

namespace App\Services\Google;

use RuntimeException;

/**
 * A failed Google API call, with a message an operator can act on. Messages
 * never contain the API key.
 */
class GoogleApiException extends RuntimeException
{
    /**
     * Google's wording for common misconfigurations, mapped to what to change.
     *
     * @var array<string, string>
     */
    private const HINTS = [
        'are blocked' => 'The key reached Google but is not allowed to call this API. In Google Cloud, enable Places API (New), Geocoding API and PageSpeed Insights API under APIs & Services > Library, then add them to the key\'s API restrictions.',
        'referer restrictions' => 'This key is restricted to websites (HTTP referrers), which only works in a browser. LeadBreave calls Google from the server: change the key\'s application restriction to IP addresses (this server\'s outbound IP) or to None while testing.',
        'IP address restrictions' => 'This key is restricted to IP addresses that do not include this server. Add the server\'s outbound IP to the key under APIs & Services > Credentials.',
        'API key not valid' => 'Google does not recognise this key. Check it was copied in full and belongs to the project where the APIs are enabled.',
        'API key is invalid' => 'Google does not recognise this key. Check it was copied in full and belongs to the project where the APIs are enabled.',
        'has not been used in project' => 'This API is not enabled on the key\'s project. Enable Places API (New), Geocoding API and PageSpeed Insights API under APIs & Services > Library, then retry in a minute.',
        'not authorized to use this API' => 'This key is restricted to a different set of APIs. Add Places API (New), Geocoding API and PageSpeed Insights API to the key\'s API restrictions.',
        'billing' => 'Billing is not enabled on the Google Cloud project. Link a billing account; Google requires one even inside the free tier.',
    ];

    public function __construct(
        string $message,
        public readonly ?int $status = null,
        private readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    /**
     * A temporary problem reaching Google, worth retrying.
     */
    public static function unreachable(string $message): self
    {
        return new self($message, retryable: true);
    }

    public static function fromResponse(string $api, ?int $status, string $googleMessage, bool $retryable = false): self
    {
        $retryable = $retryable || $status === 429 || $status >= 500;

        foreach (self::HINTS as $needle => $hint) {
            if (stripos($googleMessage, $needle) !== false) {
                return new self($hint, $status, $retryable);
            }
        }

        $prefix = match ($status) {
            429 => "{$api}: Google's rate limit or quota was reached. Try again later, or raise the quota.",
            403 => "{$api}: Google denied the key.",
            default => "{$api} returned an error.",
        };

        return new self(trim("{$prefix} {$googleMessage}"), $status, $retryable);
    }

    public static function missingKey(): self
    {
        return new self('No Google API key is set. Add it under Settings > API keys.');
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
