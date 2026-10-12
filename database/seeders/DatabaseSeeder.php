<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (! app()->environment('local', 'testing')) {
            return;
        }

        User::factory()->role(Role::Admin)->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->role(Role::Researcher)->create([
            'name' => 'Researcher',
            'email' => 'researcher@example.com',
        ]);

        User::factory()->role(Role::Sender)->create([
            'name' => 'Sender',
            'email' => 'sender@example.com',
        ]);
    }
}
