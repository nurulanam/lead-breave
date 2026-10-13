<?php

namespace App\Services\Website;

final readonly class FetchResult
{
    /**
     * @param  array<string, string>  $headers  Lower-cased names.
     */
    public function __construct(
        public string $url,
        public string $finalUrl,
        public int $status,
        public string $body,
        public array $headers,
        public float $seconds,
        public int $redirects,
        /** True when the certificate failed and the page was fetched without checking it. */
        public bool $sslBroken = false,
        public ?string $sslError = null,
    ) {}

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isHtml(): bool
    {
        $type = strtolower($this->header('content-type'));

        return $type === '' || str_contains($type, 'text/html') || str_contains($type, 'application/xhtml');
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function isHttps(): bool
    {
        return str_starts_with(strtolower($this->finalUrl), 'https://');
    }
}
