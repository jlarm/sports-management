<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\BackgroundCheck;
use App\Models\Organization;
use App\Models\User;
use App\Policies\BackgroundCheckPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(BackgroundCheckPolicy::class);
    $this->check = BackgroundCheck::factory()->for($this->org)->create();
});

function checksMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny / create require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = checksMember($this->org, $role);

    expect($this->policy->viewAny($user))->toBe($expected)
        ->and($this->policy->create($user))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('update and delete reject checks from another organization', function () {
    $admin = checksMember($this->org, OrganizationRole::Admin);
    $other = Organization::factory()->create();
    $otherCheck = BackgroundCheck::factory()->for($other)->create();

    expect($this->policy->update($admin, $this->check))->toBeTrue()
        ->and($this->policy->update($admin, $otherCheck))->toBeFalse()
        ->and($this->policy->delete($admin, $this->check))->toBeTrue()
        ->and($this->policy->delete($admin, $otherCheck))->toBeFalse();
});

test('every check fails closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = checksMember($this->org, OrganizationRole::Admin);

    expect($this->policy->viewAny($admin))->toBeFalse()
        ->and($this->policy->create($admin))->toBeFalse()
        ->and($this->policy->update($admin, $this->check))->toBeFalse()
        ->and($this->policy->delete($admin, $this->check))->toBeFalse();
});
