<?php

use App\Enums\SearchStatus;
use App\Enums\TileStatus;
use App\Enums\TilingMode;
use App\Jobs\PlanSearch;
use App\Jobs\RunSearchTile;
use App\Models\ApiCall;
use App\Models\Lead;
use App\Models\Search;
use App\Services\Google\PlacesClient;
use App\Services\Search\CostGuard;
use App\Services\Search\PlaceIngestor;
use App\Services\Search\SearchRunner;
use App\Services\Search\TilePlanner;
use App\Settings\SearchSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useGoogleKey();
    Http::preventStrayRequests();
});

function singleCellSearch(array $attributes = []): Search
{
    return Search::factory()->create([
        'tiling' => TilingMode::Single,
        'radius_m' => 2000,
        ...$attributes,
    ]);
}

it('pages through a cell and stores every business as a lead', function () {
    Http::fakeSequence(PlacesClient::URL)
        ->push(placesPage(1, 20, 'token-2'))
        ->push(placesPage(21, 5));

    $search = singleCellSearch();

    PlanSearch::dispatchSync($search->id);

    $search->refresh();

    expect($search->status)->toBe(SearchStatus::Completed)
        ->and($search->results_found)->toBe(25)
        ->and($search->leads_created)->toBe(25)
        ->and($search->api_cost_units)->toBe(2)
        ->and($search->tiles_done)->toBe(1)
        ->and($search->leads()->count())->toBe(25)
        ->and(ApiCall::forProvider('google_places')->sum('cost_units'))->toBe(2);

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'test-google-key')
        && $request->hasHeader('X-Goog-FieldMask', PlacesClient::FIELD_MASK)
        && $request['textQuery'] === $search->industry
        && isset($request['locationRestriction']['rectangle']['low'])
        && ! str_contains($request->url(), 'test-google-key'));

    Http::assertSent(fn (Request $request) => ($request['pageToken'] ?? null) === 'token-2');
});

it('maps Places fields onto the lead', function () {
    Http::fake([PlacesClient::URL => Http::response(placesPage(7, 1))]);

    PlanSearch::dispatchSync(singleCellSearch()->id);

    expect(Lead::sole())
        ->place_id->toBe('ChIJplace7')
        ->name->toBe('Business 7 Plumbing')
        ->phone_e164->toBe('+12125550007')
        ->domain->toBe('business7.com')
        ->street->toBe('107 West 34th Street')
        ->city->toBe('New York')
        ->region->toBe('NY')
        ->timezone->toBe('America/New_York')
        ->category->toBe('Plumber')
        ->review_count->toBe(120);
});

it('never fetches the same page twice', function () {
    Http::fake([PlacesClient::URL => Http::response(placesPage(1, 3))]);
    $search = singleCellSearch();

    PlanSearch::dispatchSync($search->id);
    $tile = $search->tiles()->sole();

    (new RunSearchTile($tile->id, 1))->handle(...resolveTileJobDependencies());

    Http::assertSentCount(1);
});

it('skips a page while another worker holds the cell lock', function () {
    Http::fake([PlacesClient::URL => Http::response(placesPage(1, 3))]);
    $search = singleCellSearch();
    $search->forceFill(['status' => SearchStatus::Running])->save();
    $tile = $search->tiles()->create(['lat' => 40.75, 'lng' => -73.99, 'radius_m' => 2000]);

    $lock = Cache::lock("search-tile:{$tile->id}", 120);
    $lock->get();

    RunSearchTile::dispatchSync($tile->id, 1);
    $lock->release();

    Http::assertNothingSent();
    expect($tile->fresh()->status)->toBe(TileStatus::Pending);
});

it('stops when the per-search request cap is reached', function () {
    $settings = app(SearchSettings::class);
    $settings->per_search_request_cap = 1;
    $settings->save();

    Http::fake([PlacesClient::URL => Http::response(placesPage(1, 20, 'token-2'))]);
    $search = singleCellSearch();

    PlanSearch::dispatchSync($search->id);

    expect($search->fresh())
        ->status->toBe(SearchStatus::Stopped)
        ->stop_reason->toContain('limit of 1 Google requests')
        ->api_cost_units->toBe(1);

    Http::assertSentCount(1);
});

it('stops before spending past the monthly cap', function () {
    $settings = app(SearchSettings::class);
    $settings->monthly_request_cap = 5;
    $settings->save();

    ApiCall::factory()->count(5)->create();
    ApiCall::factory()->create(['created_at' => now()->subMonth()]);

    $search = singleCellSearch();

    PlanSearch::dispatchSync($search->id);

    expect($search->fresh())
        ->status->toBe(SearchStatus::Stopped)
        ->stop_reason->toContain("this month's limit");

    Http::assertNothingSent();
});

it('splits a cell that hits the 60-result cap into four quarters', function () {
    Http::fakeSequence(PlacesClient::URL)
        ->push(placesPage(1, 20, 't2'))
        ->push(placesPage(21, 20, 't3'))
        ->push(placesPage(41, 20))
        ->push(placesPage(61, 2))
        ->push(placesPage(63, 2))
        ->push(placesPage(65, 2))
        ->push(placesPage(67, 2));

    $search = singleCellSearch();

    PlanSearch::dispatchSync($search->id);

    $search->refresh();
    $parent = $search->tiles()->whereNull('parent_id')->sole();

    expect($parent->status)->toBe(TileStatus::Split)
        ->and($parent->children()->count())->toBe(4)
        ->and($parent->children()->pluck('radius_m')->unique()->all())->toBe([1000])
        ->and($search->tiles_total)->toBe(5)
        ->and($search->tiles_done)->toBe(5)
        ->and($search->status)->toBe(SearchStatus::Completed)
        ->and($search->leads_created)->toBe(68);
});

it('stops the whole search on a key problem instead of retrying each cell', function () {
    Http::fake([PlacesClient::URL => Http::response(['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.']], 400)]);

    $search = Search::factory()->create(['tiling' => TilingMode::Grid, 'industry' => 'plumber', 'radius_m' => 3000]);

    PlanSearch::dispatchSync($search->id);

    expect($search->fresh())
        ->status->toBe(SearchStatus::Stopped)
        ->stop_reason->toContain('Google does not recognise this key');

    Http::assertSentCount(1);
});

it('re-runs from scratch but keeps the leads it found before', function () {
    Http::fake([PlacesClient::URL => Http::response(placesPage(1, 4))]);
    $search = singleCellSearch();

    PlanSearch::dispatchSync($search->id);

    expect(app(SearchRunner::class)->rerun($search))->toBeTrue();

    $search->refresh();

    expect($search->status)->toBe(SearchStatus::Completed)
        ->and($search->results_found)->toBe(4)
        ->and($search->leads_created)->toBe(0)
        ->and($search->leads_linked)->toBe(0)
        ->and($search->api_cost_units)->toBe(1)
        ->and($search->tiles()->count())->toBe(1)
        ->and($search->leads()->count())->toBe(4)
        ->and(Lead::count())->toBe(4);
});

it('does not re-run a search that is still running', function () {
    $search = Search::factory()->create(['status' => SearchStatus::Running]);

    expect(app(SearchRunner::class)->rerun($search))->toBeFalse();
    Http::assertNothingSent();
});

it('ignores a search that is not pending', function () {
    $search = Search::factory()->completed()->create();

    PlanSearch::dispatchSync($search->id);

    Http::assertNothingSent();
    expect($search->fresh()->status)->toBe(SearchStatus::Completed);
});

/**
 * @return list<object>
 */
function resolveTileJobDependencies(): array
{
    return [
        app(PlacesClient::class),
        app(PlaceIngestor::class),
        app(CostGuard::class),
        app(SearchRunner::class),
        app(TilePlanner::class),
    ];
}
