<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Guardian;
use App\Models\Organization;
use App\Models\User;
use App\Policies\GuardianPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(GuardianPolicy::class);
    $this->guardian = Guardian::factory()->for($this->org)->create();
});

function guardianMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny requires owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = guardianMember($this->org, $role);

    expect($this->policy->viewAny($user))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('view/update/delete reject guardians from another organization', function () {
    $admin = guardianMember($this->org, OrganizationRole::Admin);
    $other = Organization::factory()->create();
    $otherGuardian = Guardian::factory()->for($other)->create();

    expect($this->policy->view($admin, $this->guardian))->toBeTrue()
        ->and($this->policy->view($admin, $otherGuardian))->toBeFalse()
        ->and($this->policy->update($admin, $this->guardian))->toBeTrue()
        ->and($this->policy->update($admin, $otherGuardian))->toBeFalse()
        ->and($this->policy->delete($admin, $this->guardian))->toBeTrue()
        ->and($this->policy->delete($admin, $otherGuardian))->toBeFalse();
});

test('checks fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = guardianMember($this->org, OrganizationRole::Admin);

    expect($this->policy->viewAny($admin))->toBeFalse()
        ->and($this->policy->view($admin, $this->guardian))->toBeFalse()
        ->and($this->policy->update($admin, $this->guardian))->toBeFalse()
        ->and($this->policy->delete($admin, $this->guardian))->toBeFalse();
});
