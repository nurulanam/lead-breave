<?php

use App\Support\Timezones;

it('guesses a timezone anywhere in the world', function (?string $country, ?string $region, ?float $lat, ?float $lng, ?string $expected) {
    expect(Timezones::guess($country, $region, $lat, $lng))->toBe($expected);
})->with([
    'US state map' => ['US', 'TX', 30.27, -97.74, 'America/Chicago'],
    'one-zone country' => ['IN', 'DL', 28.61, 77.21, 'Asia/Kolkata'],
    'nearest zone in a wide country' => ['AU', 'WA', -31.95, 115.86, 'Australia/Perth'],
    'nearest zone, east coast' => ['AU', 'NSW', -33.87, 151.21, 'Australia/Sydney'],
    'unknown country' => [null, null, null, null, null],
]);
