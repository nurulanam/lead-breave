<?php

namespace App\Services\Search;

use App\Enums\SearchStatus;
use App\Enums\TileStatus;
use App\Jobs\PlanSearch;
use App\Jobs\RunSearchTile;
use App\Models\Search;
use App\Models\SearchTile;
use App\Services\Google\Coordinates;
use Illuminate\Support\Facades\DB;

/**
 * Owns a search's lifecycle: planning its cells, splitting crowded ones,
 * and finishing, stopping or failing the run.
 */
class SearchRunner
{
    public function __construct(private TilePlanner $planner) {}

    /**
     * Replace the search's cells with a fresh plan, reset its counters and
     * queue the first page of every cell. Links to leads found by earlier
     * runs are kept.
     *
     * @param  list<PlannedTile>  $tiles
     */
    public function start(Search $search, array $tiles): void
    {
        DB::transaction(function () use ($search, $tiles): void {
            $search->tiles()->delete();

            $search->forceFill([
                'status' => SearchStatus::Running,
                'tiles_total' => count($tiles),
                'tiles_done' => 0,
                'results_found' => 0,
                'leads_created' => 0,
                'leads_linked' => 0,
                'api_cost_units' => 0,
                'stop_reason' => null,
                'started_at' => now(),
                'completed_at' => null,
            ])->save();

            $search->tiles()->createMany(array_map(fn (PlannedTile $tile): array => [
                'lat' => $tile->center->lat,
                'lng' => $tile->center->lng,
                'radius_m' => $tile->halfSizeM,
            ], $tiles));
        });

        $search->tiles()->pluck('id')->each(fn (int $tileId) => RunSearchTile::dispatch($tileId, 1));
    }

    /**
     * Replace a crowded cell with its four quarters.
     */
    public function split(SearchTile $tile): void
    {
        $children = $this->planner->split(new PlannedTile(new Coordinates($tile->lat, $tile->lng), $tile->radius_m));

        $created = DB::transaction(function () use ($tile, $children) {
            $created = $tile->children()->createMany(array_map(fn (PlannedTile $child): array => [
                'search_id' => $tile->search_id,
                'depth' => $tile->depth + 1,
                'lat' => $child->center->lat,
                'lng' => $child->center->lng,
                'radius_m' => $child->halfSizeM,
            ], $children));

            $tile->forceFill(['status' => TileStatus::Split, 'next_page_token' => null])->save();
            Search::query()->whereKey($tile->search_id)->increment('tiles_total', count($children));

            return $created;
        });

        $created->each(fn (SearchTile $child) => RunSearchTile::dispatch($child->id, 1));
    }

    /**
     * Count a finished cell, and complete the search when none are left.
     */
    public function tileFinished(SearchTile $tile): void
    {
        Search::query()->whereKey($tile->search_id)->increment('tiles_done');

        $stillWorking = SearchTile::query()
            ->where('search_id', $tile->search_id)
            ->whereIn('status', [TileStatus::Pending, TileStatus::Running])
            ->exists();

        if (! $stillWorking) {
            Search::query()
                ->whereKey($tile->search_id)
                ->where('status', SearchStatus::Running)
                ->update(['status' => SearchStatus::Completed, 'completed_at' => now()]);
        }
    }

    /**
     * Queue a finished search to run again. Returns false while it is still running.
     */
    public function rerun(Search $search): bool
    {
        $queued = Search::query()
            ->whereKey($search->id)
            ->whereIn('status', [SearchStatus::Completed, SearchStatus::Stopped, SearchStatus::Failed])
            ->update(['status' => SearchStatus::Pending, 'stop_reason' => null]);

        if ($queued === 0) {
            return false;
        }

        PlanSearch::dispatch($search->id);
        $search->refresh();

        return true;
    }

    public function stop(Search $search, string $reason): void
    {
        $this->finishEarly($search, SearchStatus::Stopped, $reason);
    }

    public function fail(Search $search, string $reason): void
    {
        $this->finishEarly($search, SearchStatus::Failed, $reason);
    }

    private function finishEarly(Search $search, SearchStatus $status, string $reason): void
    {
        Search::query()
            ->whereKey($search->id)
            ->whereIn('status', [SearchStatus::Pending, SearchStatus::Running])
            ->update(['status' => $status, 'stop_reason' => $reason, 'completed_at' => now()]);

        $search->refresh();
    }
}
