<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BattingHand;
use App\Enums\OrganizationRole;
use App\Enums\ThrowingHand;
use App\Models\Division;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Player;
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

        Location::factory()->for($organization)->create([
            'name' => 'Bond Park Field 1',
            'address' => '801 High House Rd, Cary, NC 27513',
            'maps_link' => 'https://maps.google.com/?q=Bond+Park+Cary+NC',
        ]);

        Location::factory()->for($organization)->create([
            'name' => 'Mills Park Diamond',
            'address' => '1029 Mills Park Dr, Cary, NC 27519',
            'maps_link' => null,
        ]);

        Invitation::factory()->for($organization)->create([
            'email' => 'new-coach@example.com',
            'role' => OrganizationRole::Coach->value,
            'invited_by_user_id' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        Invitation::factory()->for($organization)->create([
            'email' => 'assistant-admin@example.com',
            'role' => OrganizationRole::Admin->value,
            'invited_by_user_id' => $user->id,
            'expires_at' => now()->addDays(3),
        ]);

        Invitation::factory()->for($organization)->revoked()->create([
            'email' => 'changed-our-mind@example.com',
            'role' => OrganizationRole::Coach->value,
            'invited_by_user_id' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        Invitation::factory()->for($organization)->accepted()->create([
            'email' => 'past-coach@example.com',
            'role' => OrganizationRole::Coach->value,
            'invited_by_user_id' => $user->id,
            'expires_at' => now()->subDays(30),
        ]);

        $players = [
            ['Lopez', 'Diego', '2014-04-12', BattingHand::Right, ThrowingHand::Right, 'CRY-001'],
            ['Bennett', 'Liam', '2013-11-22', BattingHand::Left, ThrowingHand::Left, 'CRY-002'],
            ['Patel', 'Anjali', '2014-08-03', BattingHand::Right, ThrowingHand::Right, 'CRY-003'],
            ['Nguyen', 'Mia', '2015-02-14', BattingHand::Switch, ThrowingHand::Right, 'CRY-004'],
            ['O\'Connor', 'Eoin', '2013-06-30', BattingHand::Right, ThrowingHand::Right, 'CRY-005'],
            ['Adams', 'Charlie', '2012-09-09', BattingHand::Left, ThrowingHand::Right, null],
            ['Garcia', 'Sofia', '2014-12-01', BattingHand::Right, ThrowingHand::Right, null],
        ];

        foreach ($players as [$lastName, $firstName, $dob, $bats, $throws, $externalId]) {
            $birthYear = (int) mb_substr($dob, 0, 4);

            Player::factory()->for($organization)->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'dob' => $dob,
                'graduation_year' => $birthYear + 18,
                'bats' => $bats->value,
                'throws' => $throws->value,
                'school' => 'Cary Elementary',
                'jersey_size' => 'YM',
                'external_id' => $externalId,
            ]);
        }
    }
}
