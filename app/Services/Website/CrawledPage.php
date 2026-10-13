<?php

namespace App\Services\Website;

final readonly class CrawledPage
{
    /**
     * @param  'home'|'contact'|'about'|'other'  $kind
     */
    public function __construct(public string $kind, public FetchResult $result) {}
}
