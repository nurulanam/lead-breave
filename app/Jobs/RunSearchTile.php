<?php

namespace App\Jobs;

use App\Enums\SearchStatus;
use App\Enums\TileStatus;
use App\Models\SearchTile;
use App\Services\Google\Coordinates;
use App\Services\Google\GoogleApiException;
use App\Services\Google\PlacesClient;
use App\Services\Search\CostGuard;
use App\Services\Search\PlaceIngestor;
use App\Services\Search\PlannedTile;
use App\Services\Search\SearchRunner;
use App\Services\Search\TilePlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Fetch one page of one cell. The cell's lock and its stored page number make
 * the job idempotent: a duplicate job finds the page already taken and exits,
 * so no page is fetched, or billed, twice.
 */
class RunSearchTile implements ShouldQueue
{
    use Queueable;

    /** Google returns at most three pages per query. */
    public const MAX_PAGES = 3;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public function __construct(public int $tileId, public int $page)
    {
        $this->onQueue('search');
    }

    public function handle(PlacesClient $places, PlaceIngestor $ingestor, CostGuard $guard, SearchRunner $runner, TilePlanner $planner): void
    {
        $lock = Cache::lock("search-tile:{$this->tileId}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $hasNextPage = $this->fetchPage($places, $ingestor, $guard, $runner, $planner);
        } finally {
            $lock->release();
        }

        if ($hasNextPage) {
            self::dispatch($this->tileId, $this->page + 1)->delay(now()->addSeconds(2));
        }
    }

    /**
     * Fetch and store this page. Returns whether the cell has another page to fetch.
     */
    private function fetchPage(PlacesClient $places, PlaceIngestor $ingestor, CostGuard $guard, SearchRunner $runner, TilePlanner $planner): bool
    {
        $tile = SearchTile::query()->with('search')->find($this->tileId);

        if ($tile === null || ! $this->isNextPageOf($tile)) {
            return false;
        }

        $search = $tile->search;

        if ($reason = $guard->stopReason($search)) {
            $runner->stop($search, $reason);

            return false;
        }

        $tile->forceFill(['status' => TileStatus::Running, 'attempts' => $tile->attempts + 1])->save();

        try {
            $result = $places->searchText(
                $search->industry,
                (new PlannedTile(new Coordinates($tile->lat, $tile->lng), $tile->radius_m))->rectangle(),
                $this->page > 1 ? $tile->next_page_token : null,
                $tile,
            );
        } catch (GoogleApiException $e) {
            $this->handleGoogleError($e, $tile, $runner);

            return false;
        }

        $search->increment('api_cost_units');

        $ingested = $ingestor->ingest($search, $result->places, $search->results_found + 1);

        $search->incrementEach([
            'results_found' => $ingested->found,
            'leads_created' => $ingested->created,
            'leads_linked' => $ingested->linked,
        ]);

        $tile->forceFill([
            'results' => $tile->results + count($result->places),
            'page' => $this->page + 1,
            'next_page_token' => $result->nextPageToken,
            'error' => null,
        ]);

        if ($result->nextPageToken !== null && $this->page < self::MAX_PAGES) {
            $tile->save();

            return true;
        }

        if ($planner->shouldSplit($tile->results, $tile->depth, $tile->radius_m)) {
            $tile->save();
            $runner->split($tile);
        } else {
            $tile->forceFill(['status' => TileStatus::Done, 'next_page_token' => null])->save();
        }

        $runner->tileFinished($tile);

        return false;
    }

    /**
     * After the last retry, record the failure and let the rest of the search continue.
     */
    public function failed(?Throwable $exception): void
    {
        $tile = SearchTile::query()->find($this->tileId);

        if ($tile === null || ! in_array($tile->status, [TileStatus::Pending, TileStatus::Running], true)) {
            return;
        }

        $tile->forceFill(['status' => TileStatus::Failed, 'error' => $exception?->getMessage()])->save();
        app(SearchRunner::class)->tileFinished($tile);
    }

    private function isNextPageOf(SearchTile $tile): bool
    {
        return $tile->search->status === SearchStatus::Running
            && in_array($tile->status, [TileStatus::Pending, TileStatus::Running], true)
            && $tile->page === $this->page;
    }

    /**
     * Retry temporary problems. A configuration problem (bad key, API not
     * enabled) would fail every cell the same way, so it stops the search.
     */
    private function handleGoogleError(GoogleApiException $e, SearchTile $tile, SearchRunner $runner): void
    {
        if ($e->isRetryable() && $this->attempts() < $this->tries) {
            $tile->forceFill(['error' => $e->getMessage()])->save();
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);

            return;
        }

        $tile->forceFill(['status' => TileStatus::Failed, 'error' => $e->getMessage()])->save();

        if (! $e->isRetryable()) {
            $runner->stop($tile->search, $e->getMessage());
        }

        $runner->tileFinished($tile);
    }
}
