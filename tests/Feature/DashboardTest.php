<?php

use App\Enums\Role;
use App\Filament\Widgets\PipelineOverview;
use App\Filament\Widgets\SetupChecklist;
use App\Filament\Widgets\SystemStatus;
use App\Models\Lead;
use App\Settings\ApiSettings;
use App\Settings\GeneralSettings;
use Livewire\Livewire;

it('shows the branded login page', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('on the map.');
});

it('shows admins the pipeline, setup checklist and system status', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Reply rate')
        ->assertSee('Leads by stage')
        ->assertSee('of 5 complete')
        ->assertSeeLivewire(SystemStatus::class);
});

it('hides admin widgets from other roles', function (Role $role) {
    $this->actingAs(userWithRole($role))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Leads by stage')
        ->assertDontSee('of 5 complete')
        ->assertDontSeeLivewire(SystemStatus::class);
})->with([Role::Researcher, Role::Sender]);

it('tracks setup progress from the saved settings', function () {
    userWithRole(Role::Admin);

    expect(SetupChecklist::progress())->toBe(['done' => 0, 'total' => 5]);

    $api = app(ApiSettings::class);
    $api->google_api_key = 'google-key';
    $api->save();

    $general = app(GeneralSettings::class);
    $general->postal_address = '1 Main St';
    $general->save();

    userWithRole(Role::Sender);

    expect(SetupChecklist::progress())->toBe(['done' => 3, 'total' => 5]);
});

it('flags a non-redis queue in the system status', function () {
    config(['queue.default' => 'database', 'mail.default' => 'log']);

    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(SystemStatus::class)
        ->assertSee('Horizon needs the redis driver.')
        ->assertSee('Not in use')
        ->assertSee('Emails are written to the log, not sent.');
});

it('counts found leads on the pipeline', function () {
    Lead::factory()->count(3)->create();

    expect(collect(PipelineOverview::stages())->firstWhere('key', 'found')['count'])->toBe(3);
});
