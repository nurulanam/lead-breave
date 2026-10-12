<?php

namespace Database\Factories;

use App\Enums\DataSource;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\SocialPlatform;
use App\Models\Lead;
use App\Models\Search;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Business name endings per Google Maps category.
     *
     * @var array<string, list<string>>
     */
    private const CATEGORIES = [
        'Plumber' => ['Plumbing', 'Plumbing & Drain', 'Rooter', 'Plumbing Co.'],
        'Dentist' => ['Family Dental', 'Dental Care', 'Smiles', 'Dentistry'],
        'Roofing contractor' => ['Roofing', 'Roofing & Gutters', 'Roof Pros', 'Exteriors'],
        'Landscaper' => ['Landscaping', 'Lawn & Garden', 'Outdoor Living', 'Landscape Design'],
        'HVAC contractor' => ['Heating & Air', 'HVAC', 'Climate Control', 'Comfort Systems'],
        'Electrician' => ['Electric', 'Electrical Services', 'Wiring Co.'],
        'Auto repair shop' => ['Auto Repair', 'Automotive', 'Car Care'],
    ];

    /**
     * @var list<string>
     */
    private const TIMEZONES = ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomKey(self::CATEGORIES);
        $name = self::businessName($category);
        $digits = fake()->numerify('2##555####');

        return [
            'place_id' => 'ChIJ'.Str::random(23),
            'name' => $name,
            'phone' => sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6)),
            'phone_e164' => '+1'.$digits,
            'website' => 'https://www.'.Str::slug($name, '').fake()->randomElement(['.com', '.com', '.net', '.co']),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->randomElement(['NY', 'NJ', 'CA', 'TX', 'IL', 'WA', 'FL', 'MA']),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'lat' => fake()->latitude(25, 48),
            'lng' => fake()->longitude(-123, -71),
            'timezone' => fake()->randomElement(self::TIMEZONES),
            'category' => $category,
            'rating' => fake()->randomFloat(1, 3, 5),
            'review_count' => fake()->numberBetween(0, 600),
            'maps_url' => 'https://maps.google.com/?cid='.fake()->numerify('###################'),
            'source' => LeadSource::Places,
        ];
    }

    /**
     * A plausible business name for a category, such as "Harbor Plumbing & Drain".
     */
    public static function businessName(string $category): string
    {
        $prefix = fake()->randomElement([
            fake()->lastName(), fake()->lastName()."'s", 'Northside', 'Metro', 'Precision', 'Summit',
            'Liberty', 'Harbor', 'Elite', 'Family', 'Express', 'Reliable', 'Greenway', 'Bright',
        ]);

        return $prefix.' '.fake()->randomElement(self::CATEGORIES[$category] ?? ['Services']);
    }

    /**
     * A lead in one Google Maps category, named to match it.
     */
    public function inCategory(string $category): static
    {
        return $this->state(function (array $attributes) use ($category): array {
            $name = self::businessName($category);

            return [
                'category' => $category,
                'name' => $name,
                'website' => $attributes['website'] === null ? null : 'https://www.'.Str::slug($name, '').'.com',
            ];
        });
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function audited(): static
    {
        return $this->status(LeadStatus::Audited);
    }

    public function withWebsite(): static
    {
        return $this->state(fn (array $attributes) => [
            'website' => 'https://www.'.Str::slug($attributes['name'] ?? fake()->company(), '').'.com',
        ]);
    }

    public function withoutWebsite(): static
    {
        return $this->state(fn (array $attributes) => ['website' => null]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => ['source' => LeadSource::Manual, 'place_id' => null]);
    }

    /**
     * Place the lead inside a search's area.
     */
    public function near(Search $search): static
    {
        return $this->state(fn (array $attributes) => [
            'lat' => ($search->center_lat ?? 40.75) + fake()->randomFloat(4, -0.06, 0.06),
            'lng' => ($search->center_lng ?? -73.99) + fake()->randomFloat(4, -0.08, 0.08),
            'postal_code' => $search->zip ?? $attributes['postal_code'],
        ]);
    }

    /**
     * Give the lead a primary email on its own domain, plus an optional role address.
     */
    public function withEmail(): static
    {
        return $this->afterCreating(function (Lead $lead): void {
            $domain = $lead->domain ?? Str::slug($lead->name, '').'.com';

            $lead->emails()->create([
                'email' => Str::lower(fake()->firstName()).'@'.$domain,
                'source' => DataSource::Crawl,
                'found_on_url' => 'https://'.$domain.'/contact',
                'confidence' => fake()->numberBetween(70, 98),
                'is_role' => false,
                'is_primary' => true,
            ]);

            if (fake()->boolean(50)) {
                $lead->emails()->create([
                    'email' => fake()->randomElement(['info', 'office', 'hello', 'contact']).'@'.$domain,
                    'source' => DataSource::Crawl,
                    'found_on_url' => 'https://'.$domain,
                    'confidence' => fake()->numberBetween(40, 70),
                    'is_role' => true,
                    'is_primary' => false,
                ]);
            }
        });
    }

    /**
     * Give the lead one to four social profiles.
     */
    public function withSocials(): static
    {
        return $this->afterCreating(function (Lead $lead): void {
            $handle = Str::slug($lead->name, '');

            foreach (fake()->randomElements([SocialPlatform::Facebook, SocialPlatform::Instagram, SocialPlatform::YouTube, SocialPlatform::Yelp, SocialPlatform::LinkedIn], fake()->numberBetween(1, 4)) as $platform) {
                $lead->socials()->create([
                    'platform' => $platform,
                    'url' => match ($platform) {
                        SocialPlatform::YouTube => "https://www.youtube.com/@{$handle}",
                        SocialPlatform::Yelp => "https://www.yelp.com/biz/{$handle}",
                        SocialPlatform::LinkedIn => "https://www.linkedin.com/company/{$handle}",
                        default => "https://www.{$platform->value}.com/{$handle}",
                    },
                    'handle' => $handle,
                    'source' => DataSource::Crawl,
                ]);
            }
        });
    }
}
