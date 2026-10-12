<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Search;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Realistic demo data: five completed searches with 200 leads each, spread
 * across the pipeline. For local demos and performance checks only.
 */
class DemoSeeder extends Seeder
{
    /**
     * @var list<array{industry: string, category: string, location: string, zip: string, lat: float, lng: float}>
     */
    private const SEARCHES = [
        ['industry' => 'plumber', 'category' => 'Plumber', 'location' => 'New York, NY', 'zip' => '10001', 'lat' => 40.7506, 'lng' => -73.9972],
        ['industry' => 'dentist', 'category' => 'Dentist', 'location' => 'San Francisco, CA', 'zip' => '94103', 'lat' => 37.7726, 'lng' => -122.4099],
        ['industry' => 'roofer', 'category' => 'Roofing contractor', 'location' => 'Austin, TX', 'zip' => '78701', 'lat' => 30.2711, 'lng' => -97.7437],
        ['industry' => 'landscaper', 'category' => 'Landscaper', 'location' => 'Chicago, IL', 'zip' => '60601', 'lat' => 41.8858, 'lng' => -87.6181],
        ['industry' => 'hvac', 'category' => 'HVAC contractor', 'location' => 'Seattle, WA', 'zip' => '98101', 'lat' => 47.6101, 'lng' => -122.3344],
    ];

    /**
     * How the demo leads are spread across statuses (weights sum to 100).
     *
     * @var array<string, int>
     */
    private const STATUS_WEIGHTS = [
        'new' => 18, 'enriched' => 22, 'needs_info' => 10, 'triaged' => 10, 'audited' => 10,
        'not_a_fit' => 6, 'ready' => 5, 'contacted' => 8, 'followed_up' => 5, 'replied' => 3,
        'meeting_booked' => 2, 'won' => 1,
    ];

    public function __construct(private int $leadsPerSearch = 200) {}

    public function run(): void
    {
        foreach (self::SEARCHES as $preset) {
            DB::transaction(fn () => $this->seedSearch($preset));
        }
    }

    /**
     * @param  array{industry: string, category: string, location: string, zip: string, lat: float, lng: float}  $preset
     */
    private function seedSearch(array $preset): void
    {
        $search = Search::factory()->completed()->create([
            'industry' => $preset['industry'],
            'location' => $preset['location'],
            'zip' => $preset['zip'],
            'center_lat' => $preset['lat'],
            'center_lng' => $preset['lng'],
            'results_found' => $this->leadsPerSearch,
            'leads_created' => $this->leadsPerSearch,
            'api_cost_units' => (int) ceil($this->leadsPerSearch / 20),
        ]);

        [$city, $region] = array_map('trim', explode(',', $preset['location']));

        for ($rank = 1; $rank <= $this->leadsPerSearch; $rank++) {
            $factory = Lead::factory()
                ->near($search)
                ->inCategory($preset['category'])
                ->status($this->randomStatus())
                ->state(['city' => $city, 'region' => $region]);

            $hasWebsite = fake()->boolean(82);
            $factory = $hasWebsite ? $factory->withSocials() : $factory->withoutWebsite();

            if ($hasWebsite && fake()->boolean(75)) {
                $factory = $factory->withEmail();
            }

            $search->leads()->attach($factory->create(), ['rank' => $rank, 'was_new' => true]);
        }
    }

    private function randomStatus(): LeadStatus
    {
        $roll = random_int(1, 100);

        foreach (self::STATUS_WEIGHTS as $status => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return LeadStatus::from($status);
            }
        }

        return LeadStatus::New;
    }
}
