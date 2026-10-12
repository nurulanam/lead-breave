<?php

namespace Database\Factories;

use App\Enums\TileStatus;
use App\Models\Search;
use App\Models\SearchTile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchTile>
 */
class SearchTileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'search_id' => Search::factory(),
            'lat' => fake()->latitude(40.70, 40.80),
            'lng' => fake()->longitude(-74.02, -73.93),
            'radius_m' => 1250,
            'status' => TileStatus::Pending,
        ];
    }
}
