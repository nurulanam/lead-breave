<?php

namespace Database\Factories;

use App\Models\LeadImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadImport>
 */
class LeadImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_path' => 'imports/'.fake()->uuid().'.csv',
            'original_name' => 'leads.csv',
            'mapping' => ['name' => 'Business'],
            'default_country' => 'US',
        ];
    }
}
