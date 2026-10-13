<?php

namespace App\Services\Google;

/**
 * A lat/lng box. Text Search only accepts a rectangle as a hard location restriction.
 */
final readonly class Rectangle
{
    public function __construct(public Coordinates $low, public Coordinates $high) {}

    /**
     * A square centred on a point, with sides of twice the half-size.
     */
    public static function around(Coordinates $center, int $halfSizeM): self
    {
        return new self($center->offset(-$halfSizeM, -$halfSizeM), $center->offset($halfSizeM, $halfSizeM));
    }

    /**
     * @return array{low: array{latitude: float, longitude: float}, high: array{latitude: float, longitude: float}}
     */
    public function toApi(): array
    {
        return [
            'low' => ['latitude' => round($this->low->lat, 7), 'longitude' => round($this->low->lng, 7)],
            'high' => ['latitude' => round($this->high->lat, 7), 'longitude' => round($this->high->lng, 7)],
        ];
    }
}
