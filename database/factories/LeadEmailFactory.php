<?php

namespace Database\Factories;

use App\Enums\DataSource;
use App\Models\Lead;
use App\Models\LeadEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadEmail>
 */
class LeadEmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'email' => fake()->unique()->safeEmail(),
            'source' => DataSource::Crawl,
            'found_on_url' => fake()->url(),
            'confidence' => fake()->numberBetween(30, 95),
            'is_role' => false,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => ['source' => DataSource::Manual, 'found_on_url' => null, 'confidence' => 100]);
    }
}
