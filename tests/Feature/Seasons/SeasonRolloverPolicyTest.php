<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Season;
use App\Models\User;
use App\Policies\SeasonPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(SeasonPolicy::class);
    $this->season = Season::factory()->for($this->org)->create();
});

function policyMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('rollover gate mirrors update permissions', function (OrganizationRole $role, bool $expected) {
    $user = policyMember($this->org, $role);

    expect($this->policy->rollover($user, $this->season))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('rollover is denied for a season belonging to a different organization', function () {
    $admin = policyMember($this->org, OrganizationRole::Admin);
    $other = Organization::factory()->create();
    $otherSeason = Season::factory()->for($other)->create();

    expect($this->policy->rollover($admin, $otherSeason))->toBeFalse();
});
