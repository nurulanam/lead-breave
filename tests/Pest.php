<?php

use App\Enums\Role;
use App\Models\User;
use App\Settings\ApiSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

function userWithRole(?Role $role): User
{
    return $role === null
        ? User::factory()->create()
        : User::factory()->role($role)->create();
}

/**
 * One business as Places API (New) returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakePlace(int $n, array $overrides = []): array
{
    return array_replace([
        'id' => "ChIJplace{$n}",
        'displayName' => ['text' => "Business {$n} Plumbing"],
        'nationalPhoneNumber' => sprintf('(212) 555-%04d', $n),
        'internationalPhoneNumber' => sprintf('+1 212-555-%04d', $n),
        'websiteUri' => "https://www.business{$n}.com/",
        'addressComponents' => [
            ['longText' => (string) (100 + $n), 'shortText' => (string) (100 + $n), 'types' => ['street_number']],
            ['longText' => 'West 34th Street', 'shortText' => 'W 34th St', 'types' => ['route']],
            ['longText' => 'New York', 'shortText' => 'New York', 'types' => ['locality', 'political']],
            ['longText' => 'New York', 'shortText' => 'NY', 'types' => ['administrative_area_level_1', 'political']],
            ['longText' => '10001', 'shortText' => '10001', 'types' => ['postal_code']],
            ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country', 'political']],
        ],
        'location' => ['latitude' => 40.75 + $n / 10000, 'longitude' => -73.99],
        'rating' => 4.5,
        'userRatingCount' => 120,
        'googleMapsUri' => "https://maps.google.com/?cid={$n}",
        'primaryTypeDisplayName' => ['text' => 'Plumber'],
        'businessStatus' => 'OPERATIONAL',
    ], $overrides);
}

/**
 * A Text Search response with places numbered from $first.
 *
 * @return array<string, mixed>
 */
function placesPage(int $first, int $count, ?string $nextPageToken = null): array
{
    return array_filter([
        'places' => array_map(fn (int $n) => fakePlace($n), $count > 0 ? range($first, $first + $count - 1) : []),
        'nextPageToken' => $nextPageToken,
    ], fn ($value) => $value !== null);
}

function useGoogleKey(string $key = 'test-google-key'): void
{
    $settings = app(ApiSettings::class);
    $settings->google_api_key = $key;
    $settings->save();
}
