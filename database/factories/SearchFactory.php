<?php

namespace Database\Factories;

use App\Enums\SearchStatus;
use App\Enums\TilingMode;
use App\Models\Search;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Search>
 */
class SearchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'industry' => fake()->randomElement(['plumber', 'dentist', 'roofer', 'landscaper', 'hvac']),
            'location' => 'New York, NY 10001, USA',
            'city' => 'New York',
            'zip' => '10001',
            'country' => 'US',
            'center_lat' => 40.7506,
            'center_lng' => -73.9972,
            'radius_m' => 5000,
            'tiling' => TilingMode::Grid,
            'status' => SearchStatus::Pending,
        ];
    }

    public function completed(int $tiles = 16): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SearchStatus::Completed,
            'tiles_total' => $tiles,
            'tiles_done' => $tiles,
            'started_at' => now()->subMinutes(12),
            'completed_at' => now()->subMinutes(4),
        ]);
    }
}
