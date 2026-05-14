<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\User;
use App\Policies\DivisionPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(DivisionPolicy::class);
    $this->division = Division::factory()->for($this->org)->create();
});

function divisionMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny requires membership in the current organization', function () {
    $member = divisionMember($this->org, OrganizationRole::Coach);
    $stranger = User::factory()->create();

    expect($this->policy->viewAny($member))->toBeTrue()
        ->and($this->policy->viewAny($stranger))->toBeFalse();
});

test('view requires the division to belong to the current organization', function () {
    $member = divisionMember($this->org, OrganizationRole::Coach);
    $otherDivision = Division::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->view($member, $this->division))->toBeTrue()
        ->and($this->policy->view($member, $otherDivision))->toBeFalse();
});

test('create, update, delete, restore require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = divisionMember($this->org, $role);

    expect($this->policy->create($user))->toBe($expected)
        ->and($this->policy->update($user, $this->division))->toBe($expected)
        ->and($this->policy->delete($user, $this->division))->toBe($expected)
        ->and($this->policy->restore($user, $this->division))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('management policies fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = divisionMember($this->org, OrganizationRole::Admin);

    expect($this->policy->create($admin))->toBeFalse();
});

test('management policies reject divisions from a different organization', function () {
    $admin = divisionMember($this->org, OrganizationRole::Admin);
    $otherDivision = Division::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->update($admin, $otherDivision))->toBeFalse()
        ->and($this->policy->delete($admin, $otherDivision))->toBeFalse();
});
