<?php

namespace Database\Factories;

use App\Enums\DataSource;
use App\Enums\SocialPlatform;
use App\Models\Lead;
use App\Models\LeadSocial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadSocial>
 */
class LeadSocialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $handle = fake()->unique()->userName();

        return [
            'lead_id' => Lead::factory(),
            'platform' => SocialPlatform::Facebook,
            'url' => "https://www.facebook.com/{$handle}",
            'handle' => $handle,
            'source' => DataSource::Crawl,
        ];
    }
}
