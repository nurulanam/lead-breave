<?php

use App\Support\Domain;

it('extracts the domain used for dedupe', function (?string $url, ?string $expected) {
    expect(Domain::fromUrl($url))->toBe($expected);
})->with([
    'https with www' => ['https://www.Northside-Plumbing.com/contact', 'northside-plumbing.com'],
    'no scheme' => ['northsideplumbing.com', 'northsideplumbing.com'],
    'subdomain kept' => ['http://shop.example.co.uk/', 'shop.example.co.uk'],
    'trailing dot' => ['https://example.com./', 'example.com'],
    'surrounding spaces' => ['  www.example.com  ', 'example.com'],
    'blank' => ['   ', null],
    'null' => [null, null],
    'no dot in host' => ['localhost', null],
    'not a url' => ['call us', null],
]);
