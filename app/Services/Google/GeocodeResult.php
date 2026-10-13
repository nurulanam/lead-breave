<?php

namespace App\Services\Google;

final readonly class GeocodeResult
{
    public function __construct(
        public Coordinates $center,
        public string $formattedAddress,
        public ?string $postalCode,
        public ?string $city,
        public ?string $region,
        public ?string $country,
        /** Half the diagonal of Google's viewport, in metres: a sensible default search radius. */
        public int $suggestedRadiusM,
    ) {}

    /**
     * Plain data for the cache, which does not unserialize objects.
     *
     * @return array{lat: float, lng: float, formatted_address: string, postal_code: string|null, city: string|null, region: string|null, country: string|null, suggested_radius_m: int}
     */
    public function toArray(): array
    {
        return [
            'lat' => $this->center->lat,
            'lng' => $this->center->lng,
            'formatted_address' => $this->formattedAddress,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'suggested_radius_m' => $this->suggestedRadiusM,
        ];
    }

    /**
     * @param  array{lat: float, lng: float, formatted_address: string, postal_code: string|null, city: string|null, region: string|null, country: string|null, suggested_radius_m: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            center: new Coordinates((float) $data['lat'], (float) $data['lng']),
            formattedAddress: $data['formatted_address'],
            postalCode: $data['postal_code'],
            city: $data['city'],
            region: $data['region'],
            country: $data['country'],
            suggestedRadiusM: (int) $data['suggested_radius_m'],
        );
    }
}
