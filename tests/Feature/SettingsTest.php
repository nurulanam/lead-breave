<?php

use App\Enums\Role;
use App\Filament\Pages\ManageApiKeys;
use App\Filament\Pages\ManageGeneralSettings;
use App\Settings\ApiSettings;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;

function storeApiKey(string $key, ?string $value): void
{
    $settings = app(ApiSettings::class);
    $settings->{$key} = $value;
    $settings->save();
}

it('encrypts API keys at rest', function () {
    storeApiKey('google_api_key', 'AIza-secret-123');

    $raw = DB::table('settings')
        ->where('group', 'api')
        ->where('name', 'google_api_key')
        ->value('payload');

    expect($raw)->not->toContain('AIza-secret-123')
        ->and(app(ApiSettings::class)->google_api_key)->toBe('AIza-secret-123');
});

it('never sends saved API keys to the browser', function () {
    storeApiKey('google_api_key', 'AIza-secret-123');

    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(ManageApiKeys::class)
        ->assertSet('data.google_api_key', null)
        ->assertDontSeeHtml('AIza-secret-123');

    $this->get(ManageApiKeys::getUrl())
        ->assertOk()
        ->assertDontSee('AIza-secret-123');
});

it('keeps a saved key when the field is left blank', function () {
    storeApiKey('google_api_key', 'old-google');
    storeApiKey('anthropic_key', 'old-anthropic');

    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(ManageApiKeys::class)
        ->fillForm(['anthropic_key' => 'new-anthropic'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.anthropic_key', null);

    $settings = app(ApiSettings::class)->refresh();

    expect($settings->google_api_key)->toBe('old-google')
        ->and($settings->anthropic_key)->toBe('new-anthropic');
});

it('removes a saved key on request', function () {
    storeApiKey('anthropic_key', 'sk-ant-old');

    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(ManageApiKeys::class)
        ->fillForm(['clear_anthropic_key' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ApiSettings::class)->refresh()->anthropic_key)->toBeNull();
});

it('saves general settings', function () {
    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm([
            'company_name' => 'Breave Studio',
            'postal_address' => '1 Main St, Springfield',
            'booking_url' => 'https://cal.com/breave/30min',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(GeneralSettings::class)->refresh();

    expect($settings->company_name)->toBe('Breave Studio')
        ->and($settings->booking_url)->toBe('https://cal.com/breave/30min');
});

it('limits settings pages to admins', function (Role $role) {
    $this->actingAs(userWithRole($role));

    $this->get(ManageApiKeys::getUrl())->assertForbidden();
    $this->get(ManageGeneralSettings::getUrl())->assertForbidden();
})->with([Role::Researcher, Role::Sender]);

it('merges the old Places and PageSpeed keys into one Google key', function (?string $places, ?string $pagespeed, ?string $expected) {
    $migrator = app(SettingsMigrator::class);
    $migrator->delete('api.google_api_key');
    $migrator->addEncrypted('api.google_places_key', $places);
    $migrator->addEncrypted('api.pagespeed_key', $pagespeed);

    (require database_path('settings/2026_10_12_100049_merge_google_api_keys.php'))->up();

    expect(app(ApiSettings::class)->refresh()->google_api_key)->toBe($expected)
        ->and(DB::table('settings')->where('group', 'api')->pluck('name')->sort()->values()->all())
        ->toBe(['anthropic_key', 'google_api_key']);
})->with([
    'both set keeps the Places key' => ['places-key', 'psi-key', 'places-key'],
    'only PageSpeed set' => [null, 'psi-key', 'psi-key'],
    'only Places set' => ['places-key', null, 'places-key'],
    'neither set' => [null, null, null],
]);
