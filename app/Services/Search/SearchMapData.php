<?php

namespace App\Services\Search;

use App\Enums\TileStatus;
use App\Models\Search;
use App\Models\SearchTile;
use App\Services\Google\Coordinates;
use App\Services\Google\Rectangle;

/**
 * The JSON the search map draws: the search area and each cell's bounds and status.
 */
class SearchMapData
{
    /**
     * A starting view before any area is chosen: the most recent search, or the whole US.
     *
     * @return array{center: array{lat: float, lng: float}, zoom: int, area: null, tiles: list<never>}
     */
    public static function default(): array
    {
        $last = Search::query()->whereNotNull('center_lat')->latest()->first(['center_lat', 'center_lng']);

        return [
            'center' => $last
                ? ['lat' => (float) $last->center_lat, 'lng' => (float) $last->center_lng]
                : ['lat' => 39.5, 'lng' => -98.35],
            'zoom' => $last ? 11 : 4,
            'area' => null,
            'tiles' => [],
        ];
    }

    /**
     * @param  list<PlannedTile>  $tiles
     * @return array{center: array{lat: float, lng: float}, area: list<list<float>>, tiles: list<array{bounds: list<list<float>>, status: string}>}
     */
    public static function forPlan(Coordinates $center, int $radiusM, array $tiles): array
    {
        return [
            'center' => ['lat' => $center->lat, 'lng' => $center->lng],
            'area' => self::bounds(Rectangle::around($center, $radiusM)),
            'tiles' => array_map(fn (PlannedTile $tile): array => [
                'bounds' => self::bounds($tile->rectangle()),
                'status' => TileStatus::Pending->value,
            ], $tiles),
        ];
    }

    /**
     * @return array{center: array{lat: float, lng: float}, area: list<list<float>>, tiles: list<array{bounds: list<list<float>>, status: string}>}|null
     */
    public static function forSearch(Search $search): ?array
    {
        if ($search->center_lat === null || $search->center_lng === null) {
            return null;
        }

        $center = new Coordinates($search->center_lat, $search->center_lng);

        return [
            'center' => ['lat' => $center->lat, 'lng' => $center->lng],
            'area' => self::bounds(Rectangle::around($center, $search->radius_m)),
            'tiles' => $search->tiles()
                ->where('status', '!=', TileStatus::Split)
                ->get(['lat', 'lng', 'radius_m', 'status'])
                ->map(fn (SearchTile $tile): array => [
                    'bounds' => self::bounds(Rectangle::around(new Coordinates($tile->lat, $tile->lng), $tile->radius_m)),
                    'status' => $tile->status->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<list<float>>
     */
    private static function bounds(Rectangle $rectangle): array
    {
        return [
            [round($rectangle->low->lat, 6), round($rectangle->low->lng, 6)],
            [round($rectangle->high->lat, 6), round($rectangle->high->lng, 6)],
        ];
    }
}
