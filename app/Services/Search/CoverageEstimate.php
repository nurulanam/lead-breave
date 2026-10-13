<?php

namespace App\Services\Search;

use App\Enums\TilingMode;
use App\Jobs\RunSearchTile;
use App\Services\Google\Coordinates;
use App\Settings\SearchSettings;

/**
 * What a search will cover and roughly cost, shown before it runs. Each cell
 * costs one to three requests; cells that need splitting cost more.
 */
final readonly class CoverageEstimate
{
    /**
     * @param  list<PlannedTile>  $tiles
     */
    public function __construct(
        public array $tiles,
        public int $minRequests,
        public int $maxRequests,
        public float $minCost,
        public float $maxCost,
    ) {}

    public static function for(Coordinates $center, int $radiusM, TilingMode $mode, string $industry): self
    {
        $settings = app(SearchSettings::class);
        $tiles = app(TilePlanner::class)->plan($center, $radiusM, $mode, $industry, $settings->max_tiles);
        $min = count($tiles);
        $max = min(count($tiles) * RunSearchTile::MAX_PAGES, $settings->per_search_request_cap);

        return new self($tiles, $min, $max, $settings->estimateCost($min), $settings->estimateCost($max));
    }
}
