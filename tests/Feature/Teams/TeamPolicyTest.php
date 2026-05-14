<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(TeamPolicy::class);
    $this->season = Season::factory()->for($this->org)->create();
    $this->division = Division::factory()->for($this->org)->create();
    $this->team = Team::factory()->for($this->org)->create([
        'season_id' => $this->season->id,
        'division_id' => $this->division->id,
    ]);
});

function teamMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny and view require membership in the current organization', function () {
    $member = teamMember($this->org, OrganizationRole::Coach);
    $stranger = User::factory()->create();

    expect($this->policy->viewAny($member))->toBeTrue()
        ->and($this->policy->viewAny($stranger))->toBeFalse()
        ->and($this->policy->view($member, $this->team))->toBeTrue();
});

test('view rejects teams from another organization', function () {
    $member = teamMember($this->org, OrganizationRole::Coach);
    $otherOrg = Organization::factory()->create();
    $otherTeam = Team::factory()->for($otherOrg)->create([
        'season_id' => Season::factory()->for($otherOrg)->create()->id,
        'division_id' => Division::factory()->for($otherOrg)->create()->id,
    ]);

    expect($this->policy->view($member, $otherTeam))->toBeFalse();
});

test('create, update, delete, restore require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = teamMember($this->org, $role);

    expect($this->policy->create($user))->toBe($expected)
        ->and($this->policy->update($user, $this->team))->toBe($expected)
        ->and($this->policy->delete($user, $this->team))->toBe($expected)
        ->and($this->policy->restore($user, $this->team))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('management policies fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = teamMember($this->org, OrganizationRole::Admin);

    expect($this->policy->create($admin))->toBeFalse()
        ->and($this->policy->update($admin, $this->team))->toBeFalse();
});
