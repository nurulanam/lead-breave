<?php

namespace App\Services\Google;

/**
 * One business from a Places API (New) Text Search response.
 */
final readonly class Place
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $phone,
        public ?string $internationalPhone,
        public ?string $website,
        public ?string $street,
        public ?string $city,
        public ?string $region,
        public ?string $postalCode,
        public ?string $country,
        public ?Coordinates $location,
        public ?string $category,
        public ?float $rating,
        public ?int $reviewCount,
        public ?string $mapsUrl,
        public bool $isPermanentlyClosed,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $components = [];

        foreach ($data['addressComponents'] ?? [] as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $components[$type] ??= $type === 'administrative_area_level_1' || $type === 'country'
                    ? ($component['shortText'] ?? $component['longText'] ?? null)
                    : ($component['longText'] ?? $component['shortText'] ?? null);
            }
        }

        $street = trim(($components['street_number'] ?? '').' '.($components['route'] ?? ''));

        return new self(
            id: (string) $data['id'],
            name: (string) ($data['displayName']['text'] ?? 'Unnamed business'),
            phone: $data['nationalPhoneNumber'] ?? null,
            internationalPhone: $data['internationalPhoneNumber'] ?? null,
            website: $data['websiteUri'] ?? null,
            street: $street !== '' ? $street : null,
            city: $components['locality'] ?? $components['postal_town'] ?? $components['sublocality'] ?? null,
            region: $components['administrative_area_level_1'] ?? null,
            postalCode: $components['postal_code'] ?? null,
            country: $components['country'] ?? null,
            location: isset($data['location']['latitude'], $data['location']['longitude'])
                ? new Coordinates((float) $data['location']['latitude'], (float) $data['location']['longitude'])
                : null,
            category: $data['primaryTypeDisplayName']['text'] ?? null,
            rating: isset($data['rating']) ? (float) $data['rating'] : null,
            reviewCount: isset($data['userRatingCount']) ? (int) $data['userRatingCount'] : null,
            mapsUrl: $data['googleMapsUri'] ?? null,
            isPermanentlyClosed: ($data['businessStatus'] ?? null) === 'CLOSED_PERMANENTLY',
        );
    }
}
