<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

function teamMemberLogin(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->activeSeason = Season::factory()->for($this->org)->active()->create(['name' => 'Spring 26']);
    $this->oldSeason = Season::factory()->for($this->org)->create(['name' => 'Fall 25']);
    $this->division = Division::factory()->for($this->org)->create(['name' => '10U']);
});

test('index defaults to the active season and renders teams from that season only', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    Team::factory()->for($this->org)->create([
        'season_id' => $this->activeSeason->id,
        'division_id' => $this->division->id,
        'name' => 'Active Team',
    ]);
    Team::factory()->for($this->org)->create([
        'season_id' => $this->oldSeason->id,
        'division_id' => $this->division->id,
        'name' => 'Old Team',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/Index')
            ->where('selectedSeasonId', $this->activeSeason->id)
            ->has('teams', 1)
            ->where('teams.0.name', 'Active Team')
            ->has('seasons', 2)
            ->has('divisions', 1)
        );
});

test('index can be filtered to another season via query param', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    Team::factory()->for($this->org)->create([
        'season_id' => $this->oldSeason->id,
        'division_id' => $this->division->id,
        'name' => 'Old Team',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('teams.index', ['season' => $this->oldSeason->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedSeasonId', $this->oldSeason->id)
            ->has('teams', 1)
            ->where('teams.0.name', 'Old Team')
        );
});

test('an unknown season query param falls back to the default season', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    $otherOrg = Organization::factory()->create();
    $otherSeason = Season::factory()->for($otherOrg)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('teams.index', ['season' => $otherSeason->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedSeasonId', $this->activeSeason->id));
});

test('without an active season the index falls back to the most recent season', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    $this->activeSeason->forceFill(['is_active' => false])->save();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedSeasonId', fn ($id) => $id === $this->activeSeason->id || $id === $this->oldSeason->id)
        );
});

test('with no seasons at all the index renders an empty state', function () {
    $emptyOrg = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->organizations()->attach($emptyOrg, ['role' => OrganizationRole::Admin->value]);

    $this->actingAs($admin->fresh())
        ->withSession(['current_org_id' => $emptyOrg->id])
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedSeasonId', null)
            ->has('teams', 0)
            ->has('seasons', 0)
        );
});

test('index is forbidden for users without a current organization', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertForbidden();
});

test('admin can create a team with an auto-generated slug', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.store'), [
            'name' => '10U Red',
            'season_id' => $this->activeSeason->id,
            'division_id' => $this->division->id,
        ])
        ->assertRedirect(route('teams.index', ['season' => $this->activeSeason->id]));

    $team = Team::query()->withoutGlobalScopes()->firstOrFail();

    expect($team->name)->toBe('10U Red')
        ->and($team->slug)->toBe('10u-red')
        ->and($team->organization_id)->toBe($this->org->id)
        ->and($team->season_id)->toBe($this->activeSeason->id);
});

test('store respects an explicit slug when provided', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.store'), [
            'name' => '10U Red',
            'slug' => 'red-machine',
            'season_id' => $this->activeSeason->id,
            'division_id' => $this->division->id,
        ])
        ->assertRedirect(route('teams.index', ['season' => $this->activeSeason->id]));

    expect(Team::query()->withoutGlobalScopes()->firstOrFail()->slug)->toBe('red-machine');
});

test('coach cannot create a team', function () {
    $coach = teamMemberLogin($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.store'), [
            'name' => '10U Red',
            'season_id' => $this->activeSeason->id,
            'division_id' => $this->division->id,
        ])
        ->assertForbidden();
});

test('store rejects a season from another organization', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    $otherSeason = Season::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.index'))
        ->post(route('teams.store'), [
            'name' => 'Hijack',
            'season_id' => $otherSeason->id,
            'division_id' => $this->division->id,
        ])
        ->assertSessionHasErrors('season_id');
});

test('store rejects a division from another organization', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    $otherDivision = Division::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.index'))
        ->post(route('teams.store'), [
            'name' => 'Hijack',
            'season_id' => $this->activeSeason->id,
            'division_id' => $otherDivision->id,
        ])
        ->assertSessionHasErrors('division_id');
});

test('store rejects duplicate slug within the same season', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    Team::factory()->for($this->org)->create([
        'season_id' => $this->activeSeason->id,
        'division_id' => $this->division->id,
        'slug' => 'taken',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.index'))
        ->post(route('teams.store'), [
            'name' => 'Other',
            'slug' => 'taken',
            'season_id' => $this->activeSeason->id,
            'division_id' => $this->division->id,
        ])
        ->assertSessionHasErrors('slug');
});

test('admin can update a team and move it between divisions', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    $newDivision = Division::factory()->for($this->org)->create(['name' => '12U']);
    $team = Team::factory()->for($this->org)->create([
        'season_id' => $this->activeSeason->id,
        'division_id' => $this->division->id,
        'name' => 'Old Name',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.update', $team), [
            'name' => 'New Name',
            'season_id' => $this->activeSeason->id,
            'division_id' => $newDivision->id,
        ])
        ->assertRedirect(route('teams.index', ['season' => $this->activeSeason->id]));

    $fresh = $team->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->division_id)->toBe($newDivision->id);
});

test('admin can archive a team via soft delete', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    $team = Team::factory()->for($this->org)->create([
        'season_id' => $this->activeSeason->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('teams.destroy', $team))
        ->assertRedirect(route('teams.index', ['season' => $this->activeSeason->id]));

    expect(Team::query()->withoutGlobalScopes()->withTrashed()->find($team->id)?->trashed())
        ->toBeTrue();
});

test('teams from another organization 404 via route binding', function () {
    $admin = teamMemberLogin($this->org, OrganizationRole::Admin);
    $otherOrg = Organization::factory()->create();
    $otherTeam = Team::factory()->for($otherOrg)->create([
        'season_id' => Season::factory()->for($otherOrg)->create()->id,
        'division_id' => Division::factory()->for($otherOrg)->create()->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.update', $otherTeam), [
            'name' => 'Hijack',
            'season_id' => $this->activeSeason->id,
            'division_id' => $this->division->id,
        ])
        ->assertNotFound();
});
