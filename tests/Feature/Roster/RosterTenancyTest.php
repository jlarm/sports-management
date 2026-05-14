<?php

declare(strict_types=1);

use App\Models\Division;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->season = Season::factory()->for($this->org)->create();
    $this->division = Division::factory()->for($this->org)->create();
    $this->team = Team::factory()->for($this->org)->create([
        'season_id' => $this->season->id,
        'division_id' => $this->division->id,
    ]);
});

test('a player can only appear once on a team', function () {
    $player = Player::factory()->for($this->org)->create();
    TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $player->id]);

    expect(fn () => TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('jersey numbers are unique within a team', function () {
    $a = Player::factory()->for($this->org)->create();
    $b = Player::factory()->for($this->org)->create();

    TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $a->id,
        'jersey_number' => 7,
    ]);

    expect(fn () => TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $b->id,
        'jersey_number' => 7,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('multiple roster entries with a null jersey number coexist on the same team', function () {
    $a = Player::factory()->for($this->org)->create();
    $b = Player::factory()->for($this->org)->create();
    $c = Player::factory()->for($this->org)->create();

    TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $a->id, 'jersey_number' => null]);
    TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $b->id, 'jersey_number' => null]);
    TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $c->id, 'jersey_number' => null]);

    expect(TeamPlayer::query()->where('team_id', $this->team->id)->whereNull('jersey_number')->count())->toBe(3);
});

test('same jersey number can exist across different teams', function () {
    $otherTeam = Team::factory()->for($this->org)->create([
        'season_id' => $this->season->id,
        'division_id' => $this->division->id,
        'slug' => 'other-team',
    ]);
    $a = Player::factory()->for($this->org)->create();
    $b = Player::factory()->for($this->org)->create();

    TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $a->id, 'jersey_number' => 7]);
    TeamPlayer::create(['team_id' => $otherTeam->id, 'player_id' => $b->id, 'jersey_number' => 7]);

    expect(TeamPlayer::query()->where('jersey_number', 7)->count())->toBe(2);
});

test('Player::teams() returns the teams the player is rostered on with pivot data', function () {
    $player = Player::factory()->for($this->org)->create();
    TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
        'jersey_number' => 4,
    ]);

    app(CurrentTenant::class)->set($this->org);

    $loaded = $player->teams()->first();
    expect($loaded?->id)->toBe($this->team->id)
        ->and($loaded?->pivot->jersey_number)->toBe(4);
});

test('Team::rosterEntries() returns the raw pivot rows', function () {
    $player = Player::factory()->for($this->org)->create();
    TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
        'jersey_number' => 17,
    ]);

    $entries = $this->team->rosterEntries()->get();
    expect($entries)->toHaveCount(1)
        ->and($entries->first()?->jersey_number)->toBe(17);
});

test('TeamPlayer::team() loads the parent team', function () {
    $player = Player::factory()->for($this->org)->create();
    $entry = TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
    ]);

    app(CurrentTenant::class)->set($this->org);

    expect($entry->team?->id)->toBe($this->team->id);
});

test('Team::players() returns roster players with pivot data', function () {
    $player = Player::factory()->for($this->org)->create();
    TeamPlayer::create([
        'team_id' => $this->team->id,
        'player_id' => $player->id,
        'jersey_number' => 12,
        'primary_position' => 'SS',
        'is_captain' => true,
    ]);

    app(CurrentTenant::class)->set($this->org);

    $loaded = $this->team->players()->first();
    expect($loaded?->id)->toBe($player->id)
        ->and($loaded?->pivot->jersey_number)->toBe(12)
        ->and($loaded?->pivot->primary_position)->toBe('SS')
        ->and($loaded?->pivot->is_captain)->toBeTrue();
});
