<?php

namespace App\Jobs;

use App\Enums\SearchStatus;
use App\Models\Search;
use App\Services\Google\Coordinates;
use App\Services\Google\Geocoder;
use App\Services\Google\GoogleApiException;
use App\Services\Search\SearchRunner;
use App\Services\Search\TilePlanner;
use App\Settings\SearchSettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Locate a pending search, split its area into cells and start the run.
 */
class PlanSearch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $searchId)
    {
        $this->onQueue('search');
    }

    public function uniqueId(): string
    {
        return (string) $this->searchId;
    }

    public function handle(Geocoder $geocoder, TilePlanner $planner, SearchRunner $runner, SearchSettings $settings): void
    {
        $search = Search::query()->find($this->searchId);

        if ($search === null || $search->status !== SearchStatus::Pending) {
            return;
        }

        if ($search->center_lat === null || $search->center_lng === null) {
            try {
                $place = $geocoder->geocode($search->zip ?? $search->location, $search->country, $search);
            } catch (GoogleApiException $e) {
                if ($e->isRetryable() && $this->attempts() < $this->tries) {
                    throw $e;
                }

                $runner->fail($search, $e->getMessage());

                return;
            }

            $search->forceFill([
                'center_lat' => $place->center->lat,
                'center_lng' => $place->center->lng,
                'zip' => $search->zip ?? $place->postalCode,
            ])->save();
        }

        $tiles = $planner->plan(
            new Coordinates((float) $search->center_lat, (float) $search->center_lng),
            $search->radius_m,
            $search->tiling,
            $search->industry,
            $settings->max_tiles,
        );

        $runner->start($search, $tiles);
    }
}
