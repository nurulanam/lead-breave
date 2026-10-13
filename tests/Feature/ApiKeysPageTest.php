<?php

use App\Enums\ApiKey;
use App\Enums\Role;
use App\Filament\Pages\ManageApiKeys;
use App\Services\ApiKeys\ApiKeyTester;
use App\Services\Google\Geocoder;
use App\Settings\ApiSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const VALID_GOOGLE_KEY = 'AIzaSyA1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q';

beforeEach(function () {
    $this->actingAs(userWithRole(Role::Admin));
    Http::preventStrayRequests();
});

function fakeGoogleApis(int $placesStatus = 200): void
{
    Http::fake([
        ApiKeyTester::PLACES_URL.'*' => Http::response(
            $placesStatus === 200 ? ['id' => ApiKeyTester::PROBE_PLACE_ID] : ['error' => ['message' => 'Requests to this API places.googleapis.com method are blocked.']],
            $placesStatus,
        ),
        Geocoder::URL.'*' => Http::response(['status' => 'OK', 'results' => []]),
        ApiKeyTester::PAGESPEED_URL.'*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Invalid value for url']], 400),
    ]);
}

function googleKeyAction(string $name): TestAction
{
    return TestAction::make($name)->arguments(['key' => ApiKey::Google->value]);
}

it('never sends a saved key to the browser', function () {
    useGoogleKey(VALID_GOOGLE_KEY);

    Livewire::test(ManageApiKeys::class)
        ->assertSee('AIza••••••••••••5p6Q')
        ->assertDontSee(VALID_GOOGLE_KEY)
        ->assertDontSee('SyA1b2C3');

    $this->get(ManageApiKeys::getUrl())->assertOk()->assertDontSee(VALID_GOOGLE_KEY);
});

it('saves a new key, records who saved it, and tests it straight away', function () {
    fakeGoogleApis();

    Livewire::test(ManageApiKeys::class)
        ->callAction(googleKeyAction('replaceKey'), ['value' => "  '".VALID_GOOGLE_KEY."'\n"])
        ->assertHasNoActionErrors()
        ->assertNotified('Google Cloud is connected');

    $settings = app(ApiSettings::class)->refresh();

    expect($settings->google_api_key)->toBe(VALID_GOOGLE_KEY)
        ->and($settings->updatedBy(ApiKey::Google))->toBe(auth()->user()->name)
        ->and(array_column($settings->lastCheck(ApiKey::Google)['services'], 'ok'))->toBe([true, true, true]);
});

it('refuses a value that is not shaped like the key', function () {
    Livewire::test(ManageApiKeys::class)
        ->callAction(googleKeyAction('replaceKey'), ['value' => 'reset-password@example.com'])
        ->assertHasActionErrors(['value']);

    Http::assertNothingSent();
    expect(app(ApiSettings::class)->refresh()->google_api_key)->toBeNull();
});

it('reports a key that works for some APIs but not others', function () {
    useGoogleKey(VALID_GOOGLE_KEY);
    fakeGoogleApis(placesStatus: 403);

    Livewire::test(ManageApiKeys::class)
        ->callAction(googleKeyAction('testKey'))
        ->assertNotified('Google Cloud is partly working')
        ->assertSee('Partly working')
        ->assertSee('enable Places API (New)');

    expect(app(ApiSettings::class)->refresh()->lastCheck(ApiKey::Google)['services']['places']['ok'])->toBeFalse();
});

it('removes a key and its history', function () {
    useGoogleKey(VALID_GOOGLE_KEY);
    app(ApiSettings::class)->recordCheck(ApiKey::Google, ['places' => ['ok' => true, 'message' => 'Connected']]);

    Livewire::test(ManageApiKeys::class)
        ->callAction(googleKeyAction('removeKey'))
        ->assertNotified('Google Cloud key removed')
        ->assertSee('Not connected');

    $settings = app(ApiSettings::class)->refresh();

    expect($settings->google_api_key)->toBeNull()
        ->and($settings->lastCheck(ApiKey::Google))->toBeNull();
});

it('forgets the old test result when a key is replaced', function () {
    $settings = app(ApiSettings::class);
    $settings->replace(ApiKey::Google, VALID_GOOGLE_KEY, null);
    $settings->recordCheck(ApiKey::Google, ['places' => ['ok' => true, 'message' => 'Connected']]);

    $settings->replace(ApiKey::Google, 'AIza'.str_repeat('z', 35), null);

    expect($settings->lastCheck(ApiKey::Google))->toBeNull();
});

it('offers only "Add key" for a service with no key', function () {
    Livewire::test(ManageApiKeys::class)
        ->assertSee('Add key')
        ->assertDontSee('Test connection')
        ->assertDontSee('Replace key');
});

it('does not let anyone but admins change keys', function () {
    $this->actingAs(userWithRole(Role::Researcher));

    $this->get(ManageApiKeys::getUrl())->assertForbidden();
});
