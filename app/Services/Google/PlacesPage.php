<?php

namespace App\Services\Google;

final readonly class PlacesPage
{
    /**
     * @param  list<Place>  $places
     */
    public function __construct(
        public array $places,
        public ?string $nextPageToken,
    ) {}
}
