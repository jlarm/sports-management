<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
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

test('the same role cannot be assigned twice to the same user on the same team', function () {
    $user = User::factory()->create();

    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    expect(fn () => TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('the same user can hold multiple distinct roles on a team', function () {
    $user = User::factory()->create();

    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]);
    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::TeamAdmin->value,
    ]);

    expect(TeamUser::query()
        ->where('team_id', $this->team->id)
        ->where('user_id', $user->id)
        ->count())->toBe(2);
});

test('the same user can be head coach on multiple teams', function () {
    $otherTeam = Team::factory()->for($this->org)->create([
        'season_id' => $this->season->id,
        'division_id' => $this->division->id,
        'slug' => 'second',
    ]);
    $user = User::factory()->create();

    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]);
    TeamUser::create([
        'team_id' => $otherTeam->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    expect(TeamUser::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('Team::teamMembers() returns coaches with role pivot data', function () {
    $user = User::factory()->create();
    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::AssistantCoach->value,
    ]);

    app(CurrentTenant::class)->set($this->org);

    $loaded = $this->team->teamMembers()->first();
    expect($loaded?->id)->toBe($user->id)
        ->and($loaded?->pivot->role)->toBe(TeamRole::AssistantCoach);
});

test('Team::coachingStaff() returns raw pivot rows', function () {
    $user = User::factory()->create();
    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    expect($this->team->coachingStaff()->count())->toBe(1);
});

test('User::coachedTeams() returns the teams a coach belongs to', function () {
    $user = User::factory()->create();
    TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    app(CurrentTenant::class)->set($this->org);

    $teams = $user->coachedTeams()->get();
    expect($teams)->toHaveCount(1)
        ->and($teams->first()?->id)->toBe($this->team->id);
});

test('TeamUser::team() and TeamUser::user() resolve their parents', function () {
    $user = User::factory()->create();
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $user->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    app(CurrentTenant::class)->set($this->org);

    expect($entry->team?->id)->toBe($this->team->id)
        ->and($entry->user?->id)->toBe($user->id);
});
