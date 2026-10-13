<?php

use App\Enums\TilingMode;
use App\Services\Google\Coordinates;
use App\Services\Search\PlannedTile;
use App\Services\Search\TilePlanner;

$center = new Coordinates(40.7506, -73.9972);

it('uses one cell covering the whole area in single mode', function () use ($center) {
    $tiles = (new TilePlanner)->plan($center, 5000, TilingMode::Single, 'plumber', 64);

    expect($tiles)->toHaveCount(1)
        ->and($tiles[0]->center)->toEqual($center)
        ->and($tiles[0]->halfSizeM)->toBe(5000);
});

it('sizes grid cells by how densely the trade is listed', function (string $industry, int $expectedTiles, int $expectedHalfSize) use ($center) {
    $tiles = (new TilePlanner)->plan($center, 5000, TilingMode::Grid, $industry, 400);

    expect($tiles)->toHaveCount($expectedTiles)
        ->and($tiles[0]->halfSizeM)->toBe($expectedHalfSize);
})->with([
    'dense trade' => ['Dentist', 81, 556],
    'default trade' => ['plumber', 16, 1250],
    'sparse trade' => ['roofing contractor', 4, 2500],
]);

it('enlarges cells to stay within the tile cap', function () use ($center) {
    $tiles = (new TilePlanner)->plan($center, 5000, TilingMode::Grid, 'restaurant', 64);

    expect($tiles)->toHaveCount(64)
        ->and($tiles[0]->halfSizeM)->toBe(625);
});

it('tiles the area edge to edge without gaps', function () use ($center) {
    $tiles = (new TilePlanner)->plan($center, 3000, TilingMode::Grid, 'plumber', 64);

    $south = min(array_map(fn (PlannedTile $tile) => $tile->rectangle()->low->lat, $tiles));
    $north = max(array_map(fn (PlannedTile $tile) => $tile->rectangle()->high->lat, $tiles));

    expect($center->distanceTo(new Coordinates($south, $center->lng)))->toEqualWithDelta(3000, 5)
        ->and($center->distanceTo(new Coordinates($north, $center->lng)))->toEqualWithDelta(3000, 5);
});

it('splits a cell into four quarters inside it', function () use ($center) {
    $parent = new PlannedTile($center, 2000);
    $children = (new TilePlanner)->split($parent);

    expect($children)->toHaveCount(4);

    foreach ($children as $child) {
        expect($child->halfSizeM)->toBe(1000)
            ->and($center->distanceTo($child->center))->toEqualWithDelta(sqrt(2) * 1000, 2);
    }
});

it('splits only a full cell that is deep and large enough to split', function (int $results, int $depth, int $halfSize, bool $expected) {
    expect((new TilePlanner)->shouldSplit($results, $depth, $halfSize))->toBe($expected);
})->with([
    'hit the 60 cap' => [60, 0, 2000, true],
    'below the cap' => [59, 0, 2000, false],
    'already at max depth' => [60, 2, 2000, false],
    'quarters would be too small' => [60, 0, 400, false],
]);
