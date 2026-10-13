<?php

namespace App\Services\Google;

final readonly class Coordinates
{
    private const EARTH_RADIUS_M = 6371000;

    private const METRES_PER_DEGREE_LAT = 111320;

    public function __construct(public float $lat, public float $lng) {}

    /**
     * The point a given distance north and east of this one (negative values go south and west).
     */
    public function offset(float $northM, float $eastM): self
    {
        return new self(
            $this->lat + $northM / self::METRES_PER_DEGREE_LAT,
            $this->lng + $eastM / (self::METRES_PER_DEGREE_LAT * cos(deg2rad($this->lat))),
        );
    }

    /**
     * Great-circle distance in metres.
     */
    public function distanceTo(self $other): float
    {
        $dLat = deg2rad($other->lat - $this->lat);
        $dLng = deg2rad($other->lng - $this->lng);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
