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
        /** Google's recommended view of the place: a whole country, a city or a postal area. */
        public ?Rectangle $viewport = null,
    ) {}

    /**
     * Plain data for the cache, which does not unserialize objects.
     *
     * @return array<string, mixed>
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
            'viewport' => $this->viewport ? [
                [$this->viewport->low->lat, $this->viewport->low->lng],
                [$this->viewport->high->lat, $this->viewport->high->lng],
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $viewport = $data['viewport'] ?? null;

        return new self(
            center: new Coordinates((float) $data['lat'], (float) $data['lng']),
            formattedAddress: (string) $data['formatted_address'],
            postalCode: $data['postal_code'] ?? null,
            city: $data['city'] ?? null,
            region: $data['region'] ?? null,
            country: $data['country'] ?? null,
            suggestedRadiusM: (int) $data['suggested_radius_m'],
            viewport: is_array($viewport) ? new Rectangle(
                new Coordinates((float) $viewport[0][0], (float) $viewport[0][1]),
                new Coordinates((float) $viewport[1][0], (float) $viewport[1][1]),
            ) : null,
        );
    }
}
