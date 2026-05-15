<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;

function rolloverAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->source = Season::factory()->for($this->org)->active()->create([
        'name' => 'Spring 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-06-30',
    ]);
    $this->divisionA = Division::factory()->for($this->org)->create(['name' => '10U']);
    $this->divisionB = Division::factory()->for($this->org)->create(['name' => '12U']);

    $this->team10uRed = Team::factory()->for($this->org)->create([
        'season_id' => $this->source->id,
        'division_id' => $this->divisionA->id,
        'name' => '10U Red',
        'slug' => 'spring-10u-red',
    ]);
    $this->team12uGold = Team::factory()->for($this->org)->create([
        'season_id' => $this->source->id,
        'division_id' => $this->divisionB->id,
        'name' => '12U Gold',
        'slug' => 'spring-12u-gold',
    ]);

    $this->player1 = Player::factory()->for($this->org)->create();
    $this->player2 = Player::factory()->for($this->org)->create();
    TeamPlayer::create(['team_id' => $this->team10uRed->id, 'player_id' => $this->player1->id, 'jersey_number' => 7]);
    TeamPlayer::create(['team_id' => $this->team10uRed->id, 'player_id' => $this->player2->id, 'jersey_number' => 9, 'is_captain' => true]);
    TeamPlayer::create(['team_id' => $this->team12uGold->id, 'player_id' => $this->player1->id, 'jersey_number' => 11]);
});

test('show page returns teams grouped by division and the org divisions list', function () {
    $admin = rolloverAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('seasons.rollover.show', $this->source))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seasons/Rollover')
            ->where('source_season.id', $this->source->id)
            ->has('teams_by_division', 2)
            ->has('divisions', 2)
        );
});

test('rollover creates a new active season, deactivates source, and clones teams + selected rosters', function () {
    $admin = rolloverAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.rollover.store', $this->source), [
            'name' => 'Fall 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'clone_teams' => true,
            'clone_roster_division_ids' => [$this->divisionA->id],
        ])
        ->assertRedirectToRoute('seasons.index');

    expect($this->source->fresh()?->is_active)->toBeFalse();

    $newSeason = Season::query()->where('name', 'Fall 2026')->firstOrFail();
    expect($newSeason->is_active)->toBeTrue()
        ->and($newSeason->organization_id)->toBe($this->org->id);

    $newTeams = Team::query()->where('season_id', $newSeason->id)->get();
    expect($newTeams)->toHaveCount(2);

    $newRed = $newTeams->firstWhere('slug', 'spring-10u-red');
    expect($newRed?->rosterEntries()->count())->toBe(2)
        ->and($newRed?->rosterEntries()->first()?->jersey_number)->toBeIn([7, 9]);

    $newGold = $newTeams->firstWhere('slug', 'spring-12u-gold');
    expect($newGold?->rosterEntries()->count())->toBe(0);

    expect(
        AuditLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'season.rolled_over')
            ->count()
    )->toBe(1);
});

test('rollover without clone_teams creates an empty season', function () {
    $admin = rolloverAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.rollover.store', $this->source), [
            'name' => 'Winter 2026',
            'start_date' => '2026-12-01',
            'end_date' => '2027-02-28',
            'clone_teams' => false,
        ])
        ->assertRedirect();

    $newSeason = Season::query()->where('name', 'Winter 2026')->firstOrFail();
    expect(Team::query()->where('season_id', $newSeason->id)->count())->toBe(0);
});

test('rollover rejects duplicate season names within the same org', function () {
    $admin = rolloverAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('seasons.rollover.show', $this->source))
        ->post(route('seasons.rollover.store', $this->source), [
            'name' => $this->source->name,
            'start_date' => '2027-03-01',
            'end_date' => '2027-06-30',
            'clone_teams' => false,
        ])
        ->assertSessionHasErrors('name');
});

test('rollover rejects end_date before start_date', function () {
    $admin = rolloverAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('seasons.rollover.show', $this->source))
        ->post(route('seasons.rollover.store', $this->source), [
            'name' => 'Bad dates',
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-01',
            'clone_teams' => false,
        ])
        ->assertSessionHasErrors('end_date');
});

test('rollover rejects clone_roster_division_ids from a different organization', function () {
    $admin = rolloverAdminLogin($this->org);
    $other = Organization::factory()->create();
    $otherDivision = Division::factory()->for($other)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('seasons.rollover.show', $this->source))
        ->post(route('seasons.rollover.store', $this->source), [
            'name' => 'Fall 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'clone_teams' => true,
            'clone_roster_division_ids' => [$otherDivision->id],
        ])
        ->assertSessionHasErrors('clone_roster_division_ids.0');
});

test('coach cannot start a rollover', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('seasons.rollover.show', $this->source))
        ->assertForbidden();

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.rollover.store', $this->source), [
            'name' => 'Fall 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'clone_teams' => false,
        ])
        ->assertForbidden();
});

test('rollover never leaves more than one active season in the org', function () {
    $admin = rolloverAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.rollover.store', $this->source), [
            'name' => 'Fall 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'clone_teams' => false,
        ])
        ->assertRedirect();

    expect(Season::query()->where('is_active', true)->count())->toBe(1);
});
