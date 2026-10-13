<?php

namespace App\Services\Search;

use App\Services\Google\Coordinates;
use App\Services\Google\Rectangle;

final readonly class PlannedTile
{
    public function __construct(public Coordinates $center, public int $halfSizeM) {}

    public function rectangle(): Rectangle
    {
        return Rectangle::around($this->center, $this->halfSizeM);
    }
}
