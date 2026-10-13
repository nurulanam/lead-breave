<?php

namespace App\Services\Search;

final readonly class IngestResult
{
    public function __construct(
        public int $found,
        public int $created,
        public int $linked,
    ) {}
}
