<?php

namespace App\Services\Website;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Fetches one page politely and safely. Redirects are followed by hand so
 * every hop passes the guard, each connection is pinned to the address the
 * guard approved, pages over the size cap are refused, and requests to the
 * same host are spaced out. Ported from LeadMap's Http_Fetcher.
 */
class Fetcher
{
    private const MAX_REDIRECTS = 3;

    /** cURL errors that mean the certificate, not the connection, is the problem. */
    private const SSL_ERRORS = ['SSL', 'certificate', 'cURL error 35', 'cURL error 51', 'cURL error 58', 'cURL error 60'];

    /** @var array<string, float> */
    private array $lastRequestAt = [];

    private ?float $deadline = null;

    public function __construct(private UrlGuard $guard) {}

    public function setBudget(int $seconds): void
    {
        $this->deadline = microtime(true) + max(5, $seconds);
    }

    /**
     * Depends on the clock, so the answer changes between calls.
     *
     * @phpstan-impure
     */
    public function outOfTime(): bool
    {
        return $this->deadline !== null && microtime(true) >= $this->deadline;
    }

    /**
     * @throws BlockedUrl
     * @throws FetchFailed
     */
    public function fetch(string $url): FetchResult
    {
        $original = $url;

        for ($redirects = 0; ; $redirects++) {
            if ($this->outOfTime()) {
                throw new FetchFailed('The time allowed for this website ran out.');
            }

            $safe = $this->guard->check($url);
            $this->throttle($safe->host);

            $started = microtime(true);
            [$response, $sslError] = $this->request($safe);

            $location = $response->header('Location');

            if (in_array($response->status(), [301, 302, 303, 307, 308], true) && $location !== '') {
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new FetchFailed('The website redirected too many times.');
                }

                $url = self::absolutize($location, $safe->url) ?? throw new FetchFailed('The website sent a redirect that could not be followed.');

                continue;
            }

            return new FetchResult(
                url: $original,
                finalUrl: $safe->url,
                status: $response->status(),
                body: $response->body(),
                headers: array_change_key_case(array_map(fn (array $values): string => (string) reset($values), $response->headers())),
                seconds: round(microtime(true) - $started, 3),
                redirects: $redirects,
                sslBroken: $sslError !== null,
                sslError: $sslError,
            );
        }
    }

    /**
     * Resolve a link against the page it appears on. Null for non-web schemes.
     */
    public static function absolutize(string $href, string $base): ?string
    {
        $href = trim($href);

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if ($href === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return null;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';

        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }

        $root = $scheme.'://'.$parts['host'];

        if (str_starts_with($href, '/')) {
            return $root.$href;
        }

        $path = (string) ($parts['path'] ?? '/');
        $directory = substr($path, 0, (int) strrpos($path, '/') + 1) ?: '/';

        return $root.$directory.$href;
    }

    /**
     * @return array{0: Response, 1: string|null} The response, and the SSL error if verification had to be skipped.
     *
     * @throws FetchFailed
     */
    private function request(SafeUrl $safe): array
    {
        try {
            return [$this->send($safe, verify: true), null];
        } catch (ConnectionException $e) {
            if (! $this->isSslError($e->getMessage())) {
                throw new FetchFailed($this->describe($e->getMessage()));
            }

            try {
                return [$this->send($safe, verify: false), $this->sslSummary($e->getMessage())];
            } catch (Throwable $retry) {
                throw new FetchFailed($this->describe($retry->getMessage()));
            }
        } catch (RuntimeException $e) {
            throw new FetchFailed($this->describe($e->getMessage()));
        }
    }

    private function send(SafeUrl $safe, bool $verify): Response
    {
        $maxBytes = (int) config('services.website_check.max_bytes');
        $ip = str_contains($safe->ip, ':') ? "[{$safe->ip}]" : $safe->ip;

        return Http::withUserAgent('LeadBreaveBot/1.0 (+'.config('app.url').'; lead research)')
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml', 'Accept-Language' => 'en;q=0.9'])
            ->timeout((int) config('services.website_check.timeout_seconds'))
            ->connectTimeout(5)
            ->withOptions([
                'allow_redirects' => false,
                'verify' => $verify,
                'http_errors' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$safe->host}:{$safe->port}:{$ip}"]],
                'on_headers' => function (ResponseInterface $response) use ($maxBytes): void {
                    if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                        throw new RuntimeException('The page is larger than 2 MB.');
                    }
                },
                'progress' => function (int $total, int $downloaded) use ($maxBytes): void {
                    if ($downloaded > $maxBytes) {
                        throw new RuntimeException('The page is larger than 2 MB.');
                    }
                },
            ])
            ->get($safe->url);
    }

    /**
     * Space out requests to the same host.
     */
    private function throttle(string $host): void
    {
        $delayMs = (int) config('services.website_check.host_delay_ms');
        $last = $this->lastRequestAt[$host] ?? null;

        if ($last !== null && $delayMs > 0) {
            $wait = $delayMs - (microtime(true) - $last) * 1000;

            if ($wait > 0) {
                usleep((int) ($wait * 1000));
            }
        }

        $this->lastRequestAt[$host] = microtime(true);
    }

    private function isSslError(string $message): bool
    {
        foreach (self::SSL_ERRORS as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function sslSummary(string $message): string
    {
        return match (true) {
            stripos($message, 'expired') !== false => 'The SSL certificate has expired.',
            stripos($message, 'self') !== false => 'The SSL certificate is self-signed.',
            stripos($message, 'name') !== false || stripos($message, 'match') !== false => 'The SSL certificate is for a different domain.',
            default => 'The SSL certificate is not trusted by browsers.',
        };
    }

    private function describe(string $message): string
    {
        return match (true) {
            stripos($message, 'larger than') !== false => 'The page is larger than 2 MB.',
            stripos($message, 'timed out') !== false, stripos($message, 'cURL error 28') !== false => 'The website took too long to respond.',
            stripos($message, 'refused') !== false, stripos($message, 'cURL error 7') !== false => 'The website refused the connection.',
            stripos($message, 'resolve') !== false, stripos($message, 'cURL error 6') !== false => 'The domain does not resolve.',
            default => 'The website could not be reached.',
        };
    }
}
