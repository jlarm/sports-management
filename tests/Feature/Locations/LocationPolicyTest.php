<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Policies\LocationPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(LocationPolicy::class);
    $this->location = Location::factory()->for($this->org)->create();
});

function locationMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny requires membership in the current organization', function () {
    $member = locationMember($this->org, OrganizationRole::Coach);
    $stranger = User::factory()->create();

    expect($this->policy->viewAny($member))->toBeTrue()
        ->and($this->policy->viewAny($stranger))->toBeFalse();
});

test('view requires the location to belong to the current organization', function () {
    $member = locationMember($this->org, OrganizationRole::Coach);
    $otherLocation = Location::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->view($member, $this->location))->toBeTrue()
        ->and($this->policy->view($member, $otherLocation))->toBeFalse();
});

test('create, update, delete, restore require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = locationMember($this->org, $role);

    expect($this->policy->create($user))->toBe($expected)
        ->and($this->policy->update($user, $this->location))->toBe($expected)
        ->and($this->policy->delete($user, $this->location))->toBe($expected)
        ->and($this->policy->restore($user, $this->location))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('management policies fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = locationMember($this->org, OrganizationRole::Admin);

    expect($this->policy->create($admin))->toBeFalse();
});

test('management policies reject locations from a different organization', function () {
    $admin = locationMember($this->org, OrganizationRole::Admin);
    $otherLocation = Location::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->update($admin, $otherLocation))->toBeFalse()
        ->and($this->policy->delete($admin, $otherLocation))->toBeFalse();
});
