<?php

use App\Enums\SearchStatus;
use App\Models\Lead;
use App\Models\Search;
use Database\Seeders\DemoSeeder;

it('seeds completed searches with linked leads near each search', function () {
    (new DemoSeeder(leadsPerSearch: 4))->run();

    $searches = Search::withCount('leads')->get();

    expect($searches)->toHaveCount(5)
        ->and($searches->pluck('status')->unique()->all())->toBe([SearchStatus::Completed])
        ->and($searches->pluck('leads_count')->unique()->all())->toBe([4])
        ->and(Lead::count())->toBe(20)
        ->and(Lead::has('searches', '!=', 1)->exists())->toBeFalse();

    $search = $searches->firstWhere('zip', '94103')->load('leads');

    expect($search->leads->every(fn (Lead $lead) => $lead->city === 'San Francisco'
        && abs($lead->lat - 37.7726) < 0.1))->toBeTrue();
});
