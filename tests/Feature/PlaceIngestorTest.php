<?php

use App\Enums\LeadEventType;
use App\Enums\LeadSource;
use App\Models\Lead;
use App\Models\Search;
use App\Services\Google\Place;
use App\Services\Search\PlaceIngestor;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $overrides
 */
function place(int $n, array $overrides = []): Place
{
    return Place::fromApi(fakePlace($n, $overrides));
}

it('matches an existing lead by place id, then domain, then phone', function (array $existing, array $overrides) {
    $lead = Lead::factory()->manual()->create($existing);
    $search = Search::factory()->create();

    $result = (new PlaceIngestor)->ingest($search, [place(1, $overrides)]);

    expect($result)->created->toBe(0)->linked->toBe(1)
        ->and(Lead::count())->toBe(1)
        ->and($search->leads()->sole()->is($lead))->toBeTrue()
        ->and($search->leads()->sole()->pivot->was_new)->toBe(0);
})->with([
    'same place id' => [['place_id' => 'ChIJplace1', 'website' => null, 'phone_e164' => null], []],
    'same domain' => [['website' => 'http://business1.com', 'phone_e164' => null], []],
    'same phone' => [['website' => null, 'phone_e164' => '+12125550001'], ['websiteUri' => null]],
]);

it('does not treat a shared host as the same business', function () {
    Lead::factory()->create(['website' => 'https://www.facebook.com/someone-else', 'phone_e164' => null]);

    $result = (new PlaceIngestor)->ingest(Search::factory()->create(), [place(1, ['websiteUri' => 'https://facebook.com/business1'])]);

    expect($result->created)->toBe(1)
        ->and(Lead::count())->toBe(2);
});

it('fills empty contact details without overwriting hand-entered ones', function () {
    $lead = Lead::factory()->manual()->create([
        'place_id' => 'ChIJplace1',
        'website' => 'https://hand-entered.example',
        'phone' => null,
        'phone_e164' => null,
        'rating' => 3.0,
    ]);

    (new PlaceIngestor)->ingest(Search::factory()->create(), [place(1)]);

    expect($lead->fresh())
        ->website->toBe('https://hand-entered.example')
        ->phone_e164->toBe('+12125550001')
        ->rating->toBe(4.5)
        ->source->toBe(LeadSource::Manual);
});

it('counts a business once when it appears twice on a page', function () {
    $search = Search::factory()->create();

    $result = (new PlaceIngestor)->ingest($search, [place(1), place(2, ['websiteUri' => 'https://business1.com'])]);

    expect($result)->found->toBe(2)->created->toBe(1)->linked->toBe(0)
        ->and(Lead::count())->toBe(1)
        ->and($search->leads()->count())->toBe(1);
});

it('does not link or count a lead twice for the same search', function () {
    $search = Search::factory()->create();
    $ingestor = new PlaceIngestor;

    $ingestor->ingest($search, [place(1)]);
    $second = (new PlaceIngestor)->ingest($search, [place(1)]);

    expect($second)->created->toBe(0)->linked->toBe(0)
        ->and($search->leads()->count())->toBe(1);
});

it('logs a timeline event when an earlier lead is found by a new search', function () {
    $lead = Lead::factory()->create(['place_id' => 'ChIJplace1']);
    $search = Search::factory()->create(['industry' => 'plumber', 'location' => 'New York, NY']);

    (new PlaceIngestor)->ingest($search, [place(1)]);

    expect($lead->events()->where('type', LeadEventType::LinkedToSearch)->sole()->data)
        ->toBe(['search_id' => $search->id, 'query' => 'plumber in New York, NY']);
});

it('skips businesses Google marks as permanently closed', function () {
    $result = (new PlaceIngestor)->ingest(Search::factory()->create(), [place(1, ['businessStatus' => 'CLOSED_PERMANENTLY'])]);

    expect($result->found)->toBe(0)
        ->and(Lead::count())->toBe(0);
});

it('looks up every existing match in a single query', function () {
    Lead::factory()->count(3)->create();
    $search = Search::factory()->create();
    $places = array_map(fn (int $n) => place($n), range(1, 20));

    DB::enableQueryLog();
    (new PlaceIngestor)->ingest($search, $places);
    $lookups = collect(DB::getQueryLog())->filter(fn (array $query) => str_starts_with($query['query'], 'select * from "leads"'));

    expect($lookups)->toHaveCount(1);
});
