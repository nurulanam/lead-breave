<?php

namespace Database\Factories;

use App\Enums\LeadEventType;
use App\Models\Lead;
use App\Models\LeadEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadEvent>
 */
class LeadEventFactory extends Factory
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
            'type' => LeadEventType::Created,
            'data' => ['source' => 'places'],
        ];
    }
}
