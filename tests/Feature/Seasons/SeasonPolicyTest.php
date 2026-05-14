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

function memberWithRole(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny requires membership in the current organization', function () {
    $member = memberWithRole($this->org, OrganizationRole::Coach);
    $stranger = User::factory()->create();

    expect($this->policy->viewAny($member))->toBeTrue()
        ->and($this->policy->viewAny($stranger))->toBeFalse();
});

test('view requires the season to belong to the current organization', function () {
    $member = memberWithRole($this->org, OrganizationRole::Coach);
    $otherSeason = Season::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->view($member, $this->season))->toBeTrue()
        ->and($this->policy->view($member, $otherSeason))->toBeFalse();
});

test('create, update, delete, restore, activate require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = memberWithRole($this->org, $role);

    expect($this->policy->create($user))->toBe($expected)
        ->and($this->policy->update($user, $this->season))->toBe($expected)
        ->and($this->policy->delete($user, $this->season))->toBe($expected)
        ->and($this->policy->restore($user, $this->season))->toBe($expected)
        ->and($this->policy->activate($user, $this->season))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('management policies fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = memberWithRole($this->org, OrganizationRole::Admin);

    expect($this->policy->create($admin))->toBeFalse();
});

test('management policies reject seasons from a different organization', function () {
    $admin = memberWithRole($this->org, OrganizationRole::Admin);
    $otherSeason = Season::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->update($admin, $otherSeason))->toBeFalse()
        ->and($this->policy->delete($admin, $otherSeason))->toBeFalse();
});
