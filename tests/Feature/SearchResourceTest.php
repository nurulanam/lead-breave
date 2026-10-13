<?php

use App\Enums\Role;
use App\Enums\SearchStatus;
use App\Enums\TilingMode;
use App\Filament\Pages\ManageSearchSettings;
use App\Filament\Resources\Searches\Pages\CreateSearch;
use App\Filament\Resources\Searches\Pages\ListSearches;
use App\Filament\Resources\Searches\Pages\ViewSearch;
use App\Filament\Resources\Searches\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\Searches\SearchResource;
use App\Jobs\PlanSearch;
use App\Models\Lead;
use App\Models\Search;
use App\Services\Google\Geocoder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('lets only users who run searches open them', function (Role $role, int $status) {
    $this->actingAs(userWithRole($role))
        ->get(SearchResource::getUrl())
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'researcher' => [Role::Researcher, 200],
    'sender' => [Role::Sender, 403],
]);

it('queues a new search for the current user', function () {
    Queue::fake();
    $user = userWithRole(Role::Researcher);
    $this->actingAs($user);

    Livewire::test(CreateSearch::class)
        ->fillForm([
            'industry' => '  plumber ',
            'location' => '10001',
            'radius_m' => 2000,
            'tiling' => TilingMode::Single,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(SearchResource::getUrl('view', ['record' => Search::sole()]));

    expect(Search::sole())
        ->industry->toBe('plumber')
        ->zip->toBe('10001')
        ->user_id->toBe($user->id)
        ->status->toBe(SearchStatus::Pending)
        ->tiling->toBe(TilingMode::Single);

    Queue::assertPushed(PlanSearch::class, fn (PlanSearch $job) => $job->searchId === Search::sole()->id);
});

it('requires a business type and a location', function () {
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(CreateSearch::class)
        ->fillForm(['industry' => '', 'location' => ''])
        ->call('create')
        ->assertHasFormErrors(['industry' => 'required', 'location' => 'required']);

    expect(Search::count())->toBe(0);
});

function fakeGeocodeOf10001(): void
{
    Http::fake([Geocoder::URL.'*' => Http::response([
        'status' => 'OK',
        'results' => [[
            'formatted_address' => 'New York, NY 10001, USA',
            'geometry' => [
                'location' => ['lat' => 40.7506, 'lng' => -73.9972],
                'viewport' => ['northeast' => ['lat' => 40.79, 'lng' => -73.95], 'southwest' => ['lat' => 40.71, 'lng' => -74.04]],
            ],
            'address_components' => [],
        ]],
    ])]);
}

it('previews the area as soon as a location is typed', function () {
    useGoogleKey();
    fakeGeocodeOf10001();
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(CreateSearch::class)
        ->fillForm(['industry' => 'plumber', 'tiling' => TilingMode::Grid, 'radius_m' => 5000])
        ->fillForm(['location' => '10001'])
        ->assertSchemaStateSet(['center_lat' => 40.7506, 'center_lng' => -73.9972, 'radius_m' => 5000])
        ->assertSee('New York, NY 10001, USA')
        ->assertSee('16–48');
});

it('waits for a whole ZIP code or three letters before looking anything up', function (string $partial) {
    Http::preventStrayRequests();
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(CreateSearch::class)
        ->fillForm(['location' => $partial])
        ->assertSchemaStateSet(['center_lat' => null])
        ->assertSee('Type a ZIP code or city');

    Http::assertNothingSent();
})->with(['half a ZIP' => ['100'], 'two letters' => ['Au']]);

it('says when a location cannot be found', function () {
    useGoogleKey();
    Http::fake([Geocoder::URL.'*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []])]);
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(CreateSearch::class)
        ->fillForm(['location' => 'Nowhereville'])
        ->assertSchemaStateSet(['center_lat' => null])
        ->assertSee('Google could not find');
});

it('shows a map before anything is typed', function () {
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(CreateSearch::class)
        ->assertSeeHtml('x-data="searchMap(')
        ->assertSeeHtml('wire:key="search-preview-map"');
});

it('re-runs a finished search from the list', function () {
    Queue::fake();
    $search = Search::factory()->completed()->create();
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(ListSearches::class)
        ->callAction(TestAction::make('rerun')->table($search));

    expect($search->fresh()->status)->toBe(SearchStatus::Pending);
    Queue::assertPushed(PlanSearch::class);
});

it('stops a running search and records who stopped it', function () {
    $search = Search::factory()->create(['status' => SearchStatus::Running]);
    $this->actingAs($user = userWithRole(Role::Researcher));

    Livewire::test(ViewSearch::class, ['record' => $search->getRouteKey()])
        ->assertActionVisible('stop')
        ->assertActionHidden('rerun')
        ->callAction('stop');

    expect($search->fresh())
        ->status->toBe(SearchStatus::Stopped)
        ->stop_reason->toBe("Stopped by {$user->name}.");
});

it('polls for progress only while a search is running', function (SearchStatus $status, bool $polls) {
    $search = Search::factory()->create(['status' => $status]);
    $this->actingAs(userWithRole(Role::Researcher));

    $page = Livewire::test(ViewSearch::class, ['record' => $search->getRouteKey()]);

    $polls ? $page->assertSeeHtml('wire:poll.3s="refreshProgress"') : $page->assertDontSeeHtml('wire:poll');
})->with([
    'running' => [SearchStatus::Running, true],
    'completed' => [SearchStatus::Completed, false],
]);

it('cannot delete a running search', function () {
    $search = Search::factory()->create(['status' => SearchStatus::Running]);
    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(ViewSearch::class, ['record' => $search->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);
});

it('lists the businesses a search found in ranking order', function () {
    $search = Search::factory()->completed()->create();
    $second = Lead::factory()->create(['name' => 'Second Plumbing']);
    $first = Lead::factory()->create(['name' => 'First Plumbing']);
    $search->leads()->attach($second, ['rank' => 2, 'was_new' => true]);
    $search->leads()->attach($first, ['rank' => 1, 'was_new' => false]);
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(LeadsRelationManager::class, ['ownerRecord' => $search, 'pageClass' => ViewSearch::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$first, $second], inOrder: true);
});

it('lets only admins change search limits', function (Role $role, int $status) {
    $this->actingAs(userWithRole($role))
        ->get(ManageSearchSettings::getUrl())
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'researcher' => [Role::Researcher, 403],
]);

it('explains when no worker has picked a search up', function (int $secondsWaiting, bool $showsNotice) {
    $search = Search::factory()->create(['status' => SearchStatus::Pending]);
    Search::query()->whereKey($search->id)->update(['updated_at' => now()->subSeconds($secondsWaiting)]);
    $this->actingAs(userWithRole(Role::Researcher));

    $page = Livewire::test(ViewSearch::class, ['record' => $search->getRouteKey()]);

    $showsNotice
        ? $page->assertSee('No worker has picked this search up yet.')
        : $page->assertDontSee('No worker has picked this search up yet.');
})->with([
    'just queued' => [5, false],
    'waiting a while' => [60, true],
]);
