<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BattingHand;
use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Enums\MatchAction;
use App\Enums\OrganizationRole;
use App\Enums\SubmissionStatus;
use App\Enums\TeamRole;
use App\Enums\ThrowingHand;
use App\Models\Division;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Submission;
use App\Models\SubmissionDecision;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        $springSeason = Season::factory()->for($organization)->active()->registrationOpen()->create([
            'name' => 'Spring 2026',
            'start_date' => '2026-03-01',
            'end_date' => '2026-05-31',
        ]);

        $fallSeason = Season::factory()->for($organization)->create([
            'name' => 'Fall 2025',
            'start_date' => '2025-09-01',
            'end_date' => '2025-11-30',
        ]);

        Season::factory()->for($organization)->create([
            'name' => 'Spring 2025',
            'start_date' => '2025-03-01',
            'end_date' => '2025-05-31',
        ]);

        $divisions = [];
        foreach (['8U', '10U', '12U', '14U', 'Varsity'] as $index => $name) {
            $divisions[$name] = Division::factory()->for($organization)->create([
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

        $createdPlayers = [];
        foreach ($players as [$lastName, $firstName, $dob, $bats, $throws, $externalId]) {
            $birthYear = (int) mb_substr($dob, 0, 4);

            $createdPlayers[$lastName] = Player::factory()->for($organization)->create([
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

        $teams = [
            'spring-10u-red' => [$springSeason, '10U', '10U Red'],
            'spring-10u-blue' => [$springSeason, '10U', '10U Blue'],
            'spring-12u-gold' => [$springSeason, '12U', '12U Gold'],
            'spring-14u-black' => [$springSeason, '14U', '14U Black'],
            'spring-varsity' => [$springSeason, 'Varsity', 'Varsity'],
            'fall-10u-red' => [$fallSeason, '10U', '10U Red'],
            'fall-12u-gold' => [$fallSeason, '12U', '12U Gold'],
        ];

        $createdTeams = [];
        foreach ($teams as $key => [$season, $divisionName, $teamName]) {
            $createdTeams[$key] = Team::factory()->for($organization)->create([
                'season_id' => $season->id,
                'division_id' => $divisions[$divisionName]->id,
                'name' => $teamName,
                'slug' => Str::slug($teamName),
            ]);
        }

        $roster = [
            ['spring-10u-red', 'Lopez', 7, 'SS', true],
            ['spring-10u-red', 'Nguyen', 22, 'CF', false],
            ['spring-10u-red', 'Patel', 3, '2B', false],
            ['spring-12u-gold', 'Bennett', 11, 'P', true],
            ['spring-12u-gold', 'O\'Connor', 5, 'C', false],
            ['spring-14u-black', 'Adams', 9, '1B', true],
            ['spring-14u-black', 'Garcia', 14, 'LF', false],
        ];

        foreach ($roster as [$teamKey, $playerLastName, $jersey, $position, $captain]) {
            TeamPlayer::create([
                'team_id' => $createdTeams[$teamKey]->id,
                'player_id' => $createdPlayers[$playerLastName]->id,
                'jersey_number' => $jersey,
                'primary_position' => $position,
                'is_captain' => $captain,
            ]);
        }

        $coachAssignments = [
            ['spring-10u-red', $user, TeamRole::HeadCoach],
            ['spring-10u-red', $user, TeamRole::TeamAdmin],
            ['spring-12u-gold', $user, TeamRole::HeadCoach],
        ];

        foreach ($coachAssignments as [$teamKey, $coach, $role]) {
            TeamUser::create([
                'team_id' => $createdTeams[$teamKey]->id,
                'user_id' => $coach->id,
                'role' => $role->value,
            ]);
        }

        $springForm = Form::factory()->for($organization)->published()->create([
            'title' => '2026 Spring Registration',
            'description' => 'Roster and contact info for the Spring 2026 season.',
            'schema' => [
                'fields' => [
                    [
                        'key' => 'first_name',
                        'label' => 'First name',
                        'type' => FieldType::Text->value,
                        'required' => true,
                    ],
                    [
                        'key' => 'last_name',
                        'label' => 'Last name',
                        'type' => FieldType::Text->value,
                        'required' => true,
                    ],
                    [
                        'key' => 'dob',
                        'label' => 'Date of birth',
                        'type' => FieldType::Date->value,
                        'required' => true,
                    ],
                    [
                        'key' => 'jersey_size',
                        'label' => 'Jersey size',
                        'type' => FieldType::Select->value,
                        'required' => true,
                        'options' => ['YS', 'YM', 'YL', 'AS', 'AM', 'AL', 'AXL'],
                    ],
                    [
                        'key' => 'allergies',
                        'label' => 'Allergies or medical notes',
                        'type' => FieldType::Textarea->value,
                        'required' => false,
                    ],
                    [
                        'key' => 'parent_email',
                        'label' => 'Parent / guardian email',
                        'type' => FieldType::Text->value,
                        'required' => true,
                    ],
                    [
                        'key' => 'parent_phone',
                        'label' => 'Parent / guardian phone',
                        'type' => FieldType::Text->value,
                        'required' => true,
                    ],
                    [
                        'key' => 'media_release',
                        'label' => 'I consent to team photos being shared publicly',
                        'type' => FieldType::Checkbox->value,
                        'required' => false,
                    ],
                ],
            ],
            'schema_version' => 1,
        ]);

        Form::factory()->for($organization)->create([
            'title' => 'Coach intent — Fall 2026',
            'description' => 'Quick draft to gauge returning coaches.',
            'status' => FormStatus::Draft->value,
            'schema' => [
                'fields' => [
                    [
                        'key' => 'returning',
                        'label' => 'Do you intend to coach again this fall?',
                        'type' => FieldType::Select->value,
                        'required' => true,
                        'options' => ['Yes', 'Maybe', 'No'],
                    ],
                ],
            ],
        ]);

        $springFormFresh = $springForm->fresh();
        $submissions = [
            [
                'first_name' => 'Riley',
                'last_name' => 'Carter',
                'dob' => '2014-07-19',
                'jersey_size' => 'YM',
                'allergies' => '',
                'parent_first_name' => 'Robert',
                'parent_last_name' => 'Carter',
                'parent_email' => 'rcarter@example.com',
                'parent_phone' => '919-555-0102',
                'media_release' => '1',
            ],
            [
                'first_name' => 'Sofia',
                'last_name' => 'Murphy',
                'dob' => '2013-10-04',
                'jersey_size' => 'YL',
                'allergies' => 'Peanut allergy — carries an EpiPen.',
                'parent_first_name' => 'Jenna',
                'parent_last_name' => 'Murphy',
                'parent_email' => 'jmurphy@example.com',
                'parent_phone' => '919-555-0144',
                'media_release' => '1',
            ],
            [
                'first_name' => 'Tyrese',
                'last_name' => 'Bell',
                'dob' => '2015-03-29',
                'jersey_size' => 'YS',
                'allergies' => '',
                'parent_first_name' => 'Kim',
                'parent_last_name' => 'Bell',
                'parent_email' => 'kbell@example.com',
                'parent_phone' => '919-555-0167',
                'media_release' => '',
            ],
        ];

        $createdSubmissions = [];
        foreach ($submissions as $data) {
            $createdSubmissions[] = Submission::factory()->for($organization)->for($springFormFresh)->create([
                'submitted_by_user_id' => null,
                'schema_snapshot' => $springFormFresh->schema,
                'schema_version' => $springFormFresh->schema_version,
                'data' => $data,
                'submitted_at' => now()->subDays(random_int(1, 14)),
            ]);
        }

        $murphySubmission = $createdSubmissions[1];
        $murphyPlayer = Player::factory()->for($organization)->create([
            'first_name' => 'Sofia',
            'last_name' => 'Murphy',
            'dob' => '2013-10-04',
            'jersey_size' => 'YL',
            'medical_notes' => 'Peanut allergy — carries an EpiPen.',
        ]);
        $murphyGuardian = Guardian::factory()->for($organization)->create([
            'first_name' => 'Jenna',
            'last_name' => 'Murphy',
            'email' => 'jmurphy@example.com',
            'phone' => '919-555-0144',
        ]);
        $murphyPlayer->guardians()->attach($murphyGuardian->id, ['is_primary' => true]);
        $murphySubmission->forceFill(['status' => SubmissionStatus::Processed])->save();
        SubmissionDecision::query()->create([
            'organization_id' => $organization->id,
            'submission_id' => $murphySubmission->id,
            'decided_by_user_id' => $user->id,
            'player_action' => MatchAction::Created,
            'player_id' => $murphyPlayer->id,
            'guardian_action' => MatchAction::Created,
            'guardian_id' => $murphyGuardian->id,
            'notes' => 'Seeded as a worked example of the dedupe flow.',
            'decided_at' => now()->subDays(1),
        ]);
    }
}
