<?php

namespace App\Services\Website;

final readonly class EmailCandidate
{
    public function __construct(
        public string $email,
        public string $source,
        public int $confidence,
        public bool $isRole,
        public string $foundOnUrl,
    ) {}
}
