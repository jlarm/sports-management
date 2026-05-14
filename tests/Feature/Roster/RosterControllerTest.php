<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;

function rosterMemberLogin(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

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

test('show renders the roster page with entries and available players', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $rostered = Player::factory()->for($this->org)->create(['first_name' => 'Diego', 'last_name' => 'Lopez']);
    $unrostered = Player::factory()->for($this->org)->create(['first_name' => 'Eoin', 'last_name' => 'OConnor']);

    TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $rostered->id,
        'jersey_number' => 7,
        'primary_position' => 'SS',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('teams.roster.show', $this->team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/Show')
            ->where('team.id', $this->team->id)
            ->has('rosterEntries', 1)
            ->where('rosterEntries.0.player.id', $rostered->id)
            ->where('rosterEntries.0.jersey_number', 7)
            ->where('rosterEntries.0.primary_position', 'SS')
            ->has('availablePlayers', 1)
            ->where('availablePlayers.0.id', $unrostered->id)
        );
});

test('admin can add a player to the roster', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.roster.store', $this->team), [
            'player_id' => $player->id,
            'jersey_number' => 12,
            'primary_position' => '1B',
            'is_captain' => true,
        ])
        ->assertRedirect(route('teams.roster.show', $this->team));

    $entry = TeamPlayer::query()->firstOrFail();
    expect($entry->team_id)->toBe($this->team->id)
        ->and($entry->player_id)->toBe($player->id)
        ->and($entry->jersey_number)->toBe(12)
        ->and($entry->primary_position)->toBe('1B')
        ->and($entry->is_captain)->toBeTrue();
});

test('coach cannot add a player to the roster', function () {
    $coach = rosterMemberLogin($this->org, OrganizationRole::Coach);
    $player = Player::factory()->for($this->org)->create();

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.roster.store', $this->team), [
            'player_id' => $player->id,
        ])
        ->assertForbidden();
});

test('cannot add a player from a different organization', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $foreignPlayer = Player::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.roster.store', $this->team), [
            'player_id' => $foreignPlayer->id,
        ])
        ->assertSessionHasErrors('player_id');
});

test('store rejects duplicate jersey within the same team', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $a = Player::factory()->for($this->org)->create();
    $b = Player::factory()->for($this->org)->create();
    TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $a->id,
        'jersey_number' => 7,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.roster.store', $this->team), [
            'player_id' => $b->id,
            'jersey_number' => 7,
        ])
        ->assertSessionHasErrors('jersey_number');
});

test('store rejects adding the same player twice to a team', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create();
    TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.roster.store', $this->team), [
            'player_id' => $player->id,
        ])
        ->assertSessionHasErrors('player_id');
});

test('store rejects jersey number out of range', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.roster.store', $this->team), [
            'player_id' => $player->id,
            'jersey_number' => 1000,
        ])
        ->assertSessionHasErrors('jersey_number');
});

test('admin can update a roster entry', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create();
    $entry = TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
        'jersey_number' => 3,
        'primary_position' => 'SS',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.roster.update', [$this->team, $entry]), [
            'jersey_number' => 9,
            'primary_position' => '2B',
            'is_captain' => true,
        ])
        ->assertRedirect(route('teams.roster.show', $this->team));

    $fresh = $entry->fresh();
    expect($fresh->jersey_number)->toBe(9)
        ->and($fresh->primary_position)->toBe('2B')
        ->and($fresh->is_captain)->toBeTrue();
});

test('update allows keeping the same jersey number', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create();
    $entry = TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
        'jersey_number' => 7,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.roster.update', [$this->team, $entry]), [
            'jersey_number' => 7,
            'primary_position' => 'C',
        ])
        ->assertRedirect(route('teams.roster.show', $this->team));
});

test('admin can remove a player from the roster', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create();
    $entry = TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('teams.roster.destroy', [$this->team, $entry]))
        ->assertRedirect(route('teams.roster.show', $this->team));

    expect(TeamPlayer::query()->find($entry->id))->toBeNull();
});

test('a roster entry from another team returns 404 on update', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $otherTeam = Team::factory()->for($this->org)->create([
        'season_id' => $this->season->id,
        'division_id' => $this->division->id,
        'slug' => 'blue',
    ]);
    $player = Player::factory()->for($this->org)->create();
    $otherEntry = TeamPlayer::create([
        'team_id' => $otherTeam->id,
        'player_id' => $player->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.roster.update', [$this->team, $otherEntry]), [
            'jersey_number' => 1,
        ])
        ->assertNotFound();
});

test('teams from another organization 404 on show', function () {
    $admin = rosterMemberLogin($this->org, OrganizationRole::Admin);
    $otherOrg = Organization::factory()->create();
    $otherTeam = Team::factory()->for($otherOrg)->create([
        'season_id' => Season::factory()->for($otherOrg)->create()->id,
        'division_id' => Division::factory()->for($otherOrg)->create()->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('teams.roster.show', $otherTeam))
        ->assertNotFound();
});
