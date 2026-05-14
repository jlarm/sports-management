<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\TeamRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;

function coachAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->season = Season::factory()->for($this->org)->active()->create();
    $this->division = Division::factory()->for($this->org)->create();
    $this->team = Team::factory()->for($this->org)->create([
        'season_id' => $this->season->id,
        'division_id' => $this->division->id,
        'slug' => 'red',
    ]);
});

test('show exposes coaches, available members, and role options', function () {
    $admin = coachAdminLogin($this->org);
    $assignedCoach = User::factory()->create(['name' => 'Coach Sam']);
    $assignedCoach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $unassigned = User::factory()->create(['name' => 'Coach Alex']);
    $unassigned->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $assignedCoach->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('teams.roster.show', $this->team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('coaches', 1)
            ->where('coaches.0.user.id', $assignedCoach->id)
            ->where('coaches.0.role', TeamRole::HeadCoach->value)
            ->where('coaches.0.role_label', 'Head coach')
            ->has('availableMembers', 3)
            ->has('teamRoleOptions', 3)
        );
});

test('admin can assign a coach role', function () {
    $admin = coachAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $coach->id,
            'role' => TeamRole::AssistantCoach->value,
        ])
        ->assertRedirect(route('teams.roster.show', $this->team));

    $entry = TeamUser::query()->firstOrFail();
    expect($entry->team_id)->toBe($this->team->id)
        ->and($entry->user_id)->toBe($coach->id)
        ->and($entry->role)->toBe(TeamRole::AssistantCoach);
});

test('coach without admin role cannot assign a coach', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $candidate = User::factory()->create();
    $candidate->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $candidate->id,
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertForbidden();
});

test('cannot assign a user who is not a member of the current organization', function () {
    $admin = coachAdminLogin($this->org);
    $stranger = User::factory()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $stranger->id,
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertSessionHasErrors('user_id');
});

test('rejects duplicate (team, user, role) assignments', function () {
    $admin = coachAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $coach->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $coach->id,
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertSessionHasErrors('role');
});

test('admin can change a coach role', function () {
    $admin = coachAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $coach->id,
        'role' => TeamRole::AssistantCoach->value,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.coaches.update', [$this->team, $entry]), [
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertRedirect(route('teams.roster.show', $this->team));

    expect($entry->fresh()?->role)->toBe(TeamRole::HeadCoach);
});

test('updating to a role the user already holds is rejected', function () {
    $admin = coachAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $coach->id,
        'role' => TeamRole::HeadCoach->value,
    ]);
    $assistant = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $coach->id,
        'role' => TeamRole::AssistantCoach->value,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->patch(route('teams.coaches.update', [$this->team, $assistant]), [
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertSessionHasErrors('role');
});

test('admin can remove a coach', function () {
    $admin = coachAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $coach->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('teams.coaches.destroy', [$this->team, $entry]))
        ->assertRedirect(route('teams.roster.show', $this->team));

    expect(TeamUser::query()->find($entry->id))->toBeNull();
});

test('a coach entry from another team returns 404 on update', function () {
    $admin = coachAdminLogin($this->org);
    $otherTeam = Team::factory()->for($this->org)->create([
        'season_id' => $this->season->id,
        'division_id' => $this->division->id,
        'slug' => 'blue',
    ]);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $otherEntry = TeamUser::create([
        'team_id' => $otherTeam->id,
        'user_id' => $coach->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.coaches.update', [$this->team, $otherEntry]), [
            'role' => TeamRole::AssistantCoach->value,
        ])
        ->assertNotFound();
});
