<?php

namespace App\Services\Search;

use App\Enums\TilingMode;
use App\Services\Google\Coordinates;

/**
 * Splits a search area into square cells. Google returns at most 60 results
 * (3 pages) per query, so dense trades need smaller cells to be seen in full.
 */
class TilePlanner
{
    /** A cell that returned this many results was probably cut off by Google's cap. */
    public const RESULT_CAP = 60;

    public const MAX_SPLIT_DEPTH = 2;

    public const MIN_HALF_SIZE_M = 250;

    /**
     * Cell half-size in metres by how densely a trade is listed.
     *
     * @var array<int, list<string>>
     */
    private const DENSITY = [
        600 => ['restaurant', 'cafe', 'coffee', 'bar', 'pizza', 'bakery', 'salon', 'barber', 'nail', 'spa',
            'dentist', 'doctor', 'clinic', 'chiropract', 'lawyer', 'attorney', 'real estate', 'realtor',
            'insurance', 'gym', 'fitness', 'yoga', 'store', 'shop', 'boutique', 'florist', 'pharmacy', 'hotel'],
        3000 => ['roof', 'landscap', 'lawn', 'hvac', 'heating', 'air condition', 'contractor', 'electrician',
            'pest', 'pool', 'tree', 'moving', 'movers', 'towing', 'paving', 'fence', 'solar', 'septic',
            'excavat', 'concrete', 'gutter', 'siding', 'pressure wash', 'junk'],
    ];

    private const DEFAULT_HALF_SIZE_M = 1500;

    /**
     * @return list<PlannedTile>
     */
    public function plan(Coordinates $center, int $radiusM, TilingMode $mode, string $industry, int $maxTiles): array
    {
        if ($mode === TilingMode::Single) {
            return [new PlannedTile($center, $radiusM)];
        }

        $halfSize = min($this->halfSizeFor($industry), $radiusM);
        $perSide = (int) ceil($radiusM / $halfSize);
        $maxPerSide = max(1, (int) floor(sqrt(max(1, $maxTiles))));

        if ($perSide > $maxPerSide) {
            $perSide = $maxPerSide;
        }

        $halfSize = (int) ceil($radiusM / $perSide);
        $tiles = [];

        for ($row = 0; $row < $perSide; $row++) {
            for ($column = 0; $column < $perSide; $column++) {
                $tiles[] = new PlannedTile(
                    $center->offset(-$radiusM + $halfSize * (2 * $row + 1), -$radiusM + $halfSize * (2 * $column + 1)),
                    $halfSize,
                );
            }
        }

        return $tiles;
    }

    /**
     * The four quarters of a cell.
     *
     * @return list<PlannedTile>
     */
    public function split(PlannedTile $tile): array
    {
        $half = (int) ceil($tile->halfSizeM / 2);

        return [
            new PlannedTile($tile->center->offset($half, -$half), $half),
            new PlannedTile($tile->center->offset($half, $half), $half),
            new PlannedTile($tile->center->offset(-$half, -$half), $half),
            new PlannedTile($tile->center->offset(-$half, $half), $half),
        ];
    }

    /**
     * Whether a finished cell was probably truncated by Google and can still be split.
     */
    public function shouldSplit(int $results, int $depth, int $halfSizeM): bool
    {
        return $results >= self::RESULT_CAP
            && $depth < self::MAX_SPLIT_DEPTH
            && intdiv($halfSizeM, 2) >= self::MIN_HALF_SIZE_M;
    }

    public function halfSizeFor(string $industry): int
    {
        $industry = mb_strtolower($industry);

        foreach (self::DENSITY as $halfSize => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($industry, $keyword)) {
                    return $halfSize;
                }
            }
        }

        return self::DEFAULT_HALF_SIZE_M;
    }
}
