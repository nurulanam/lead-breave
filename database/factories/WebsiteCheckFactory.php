<?php

namespace Database\Factories;

use App\Enums\WebsiteCheckType;
use App\Models\Lead;
use App\Models\WebsiteCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteCheck>
 */
class WebsiteCheckFactory extends Factory
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
            'type' => WebsiteCheckType::Crawl,
            'status' => WebsiteCheck::STATUS_OK,
            'result' => ['reachable' => true, 'status' => 200],
            'checked_at' => now(),
        ];
    }
}
