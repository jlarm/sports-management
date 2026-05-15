<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\TeamRole;
use App\Models\BackgroundCheck;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;

function gateAdminLogin(Organization $org): User
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

    $this->admin = gateAdminLogin($this->org);
    $this->coach = User::factory()->create();
    $this->coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
});

test('assigning a HeadCoach without a check is blocked', function () {
    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertSessionHasErrors('user_id');

    expect(TeamUser::query()->count())->toBe(0);
});

test('assigning an AssistantCoach without a check is blocked', function () {
    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => TeamRole::AssistantCoach->value,
        ])
        ->assertSessionHasErrors('user_id');
});

test('a flagged check still blocks the assignment', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->coach)->flagged()->create();

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertSessionHasErrors('user_id');
});

test('an expired check blocks the assignment', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->coach)->expired()->create();

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => TeamRole::AssistantCoach->value,
        ])
        ->assertSessionHasErrors('user_id');
});

test('a cleared check allows the assignment', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->coach)->cleared()->create();

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertRedirect();

    expect(TeamUser::query()->count())->toBe(1);
});

test('a TeamManager assignment never needs a check', function () {
    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => TeamRole::TeamAdmin->value,
        ])
        ->assertRedirect();

    expect(TeamUser::query()->count())->toBe(1);
});

test('promoting a TeamManager to HeadCoach also requires a check', function () {
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $this->coach->id,
        'role' => TeamRole::TeamAdmin->value,
    ]);

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->patch(route('teams.coaches.update', [$this->team, $entry]), [
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertSessionHasErrors('role');
});

test('coach store after closure ignores a non-string role payload', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->coach)->cleared()->create();

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => 123,
        ])
        ->assertSessionHasErrors('role')
        ->assertSessionDoesntHaveErrors('user_id');
});

test('coach store after closure ignores an unrecognized role string', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->coach)->cleared()->create();

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->post(route('teams.coaches.store', $this->team), [
            'user_id' => $this->coach->id,
            'role' => 'not_a_real_role',
        ])
        ->assertSessionHasErrors('role')
        ->assertSessionDoesntHaveErrors('user_id');
});

test('coach update after closure ignores a non-string role payload', function () {
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $this->coach->id,
        'role' => TeamRole::TeamAdmin->value,
    ]);

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->patch(route('teams.coaches.update', [$this->team, $entry]), [
            'role' => 123,
        ])
        ->assertSessionHasErrors('role');

    expect($entry->fresh()?->role)->toBe(TeamRole::TeamAdmin);
});

test('coach update after closure ignores an unrecognized role string', function () {
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $this->coach->id,
        'role' => TeamRole::TeamAdmin->value,
    ]);

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('teams.roster.show', $this->team))
        ->patch(route('teams.coaches.update', [$this->team, $entry]), [
            'role' => 'not_a_real_role',
        ])
        ->assertSessionHasErrors('role');
});

test('demoting a HeadCoach to TeamAdmin skips the check requirement', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->coach)->cleared()->create();
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $this->coach->id,
        'role' => TeamRole::HeadCoach->value,
    ]);

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.coaches.update', [$this->team, $entry]), [
            'role' => TeamRole::TeamAdmin->value,
        ])
        ->assertRedirect();

    expect($entry->fresh()?->role)->toBe(TeamRole::TeamAdmin);
});

test('promoting to HeadCoach succeeds when a cleared check is on file', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->coach)->cleared()->create();
    $entry = TeamUser::create([
        'team_id' => $this->team->id,
        'user_id' => $this->coach->id,
        'role' => TeamRole::TeamAdmin->value,
    ]);

    $this->actingAs($this->admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.coaches.update', [$this->team, $entry]), [
            'role' => TeamRole::HeadCoach->value,
        ])
        ->assertRedirect();

    expect($entry->fresh()?->role)->toBe(TeamRole::HeadCoach);
});
