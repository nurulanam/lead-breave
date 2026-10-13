<?php

namespace App\Services\Website;

/**
 * A URL that passed the guard, with the address it was checked against. The
 * fetcher connects to exactly that address, so DNS cannot change in between.
 */
final readonly class SafeUrl
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $ip,
    ) {}
}
