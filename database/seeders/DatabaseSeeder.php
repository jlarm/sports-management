<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Joe Lohr',
            'email' => 'joelohr01@gmail.com',
            'password' => bcrypt('password'),
        ]);

        $organization = Organization::factory()->create([
            'name' => 'Cary Trojans Baseball',
            'slug' => 'cary-trojans',
            'owner_id' => $user->id,
        ]);

        $user->organizations()->attach($organization, [
            'role' => OrganizationRole::Owner->value,
        ]);

        Season::factory()->for($organization)->active()->registrationOpen()->create([
            'name' => 'Spring 2026',
            'start_date' => '2026-03-01',
            'end_date' => '2026-05-31',
        ]);

        Season::factory()->for($organization)->create([
            'name' => 'Fall 2025',
            'start_date' => '2025-09-01',
            'end_date' => '2025-11-30',
        ]);

        Season::factory()->for($organization)->create([
            'name' => 'Spring 2025',
            'start_date' => '2025-03-01',
            'end_date' => '2025-05-31',
        ]);

        foreach (['8U', '10U', '12U', '14U', 'Varsity'] as $index => $name) {
            Division::factory()->for($organization)->create([
                'name' => $name,
                'display_order' => $index,
            ]);
        }
    }
}
