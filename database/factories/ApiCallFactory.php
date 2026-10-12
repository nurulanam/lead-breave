<?php

namespace Database\Factories;

use App\Models\ApiCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiCall>
 */
class ApiCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'google_places',
            'endpoint' => 'places:searchText',
            'cost_units' => 1,
            'status' => 200,
            'duration_ms' => fake()->numberBetween(150, 900),
        ];
    }
}
