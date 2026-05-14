<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Policies\OrganizationPolicy;

beforeEach(function () {
    $this->policy = new OrganizationPolicy;
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
});

test('viewAny and create are always allowed for authenticated users', function () {
    expect($this->policy->viewAny($this->user))->toBeTrue()
        ->and($this->policy->create($this->user))->toBeTrue();
});

test('view requires membership', function () {
    expect($this->policy->view($this->user, $this->org))->toBeFalse();

    $this->user->organizations()->attach($this->org, ['role' => OrganizationRole::Guardian->value]);

    expect($this->policy->view($this->user->fresh(), $this->org))->toBeTrue();
});

test('update requires owner or admin role', function (OrganizationRole $role, bool $expected) {
    $this->user->organizations()->attach($this->org, ['role' => $role->value]);

    expect($this->policy->update($this->user->fresh(), $this->org))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('delete, restore and forceDelete require owner role', function (OrganizationRole $role, bool $expected) {
    $this->user->organizations()->attach($this->org, ['role' => $role->value]);
    $user = $this->user->fresh();

    expect($this->policy->delete($user, $this->org))->toBe($expected)
        ->and($this->policy->restore($user, $this->org))->toBe($expected)
        ->and($this->policy->forceDelete($user, $this->org))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, false],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('update returns false when user is not a member', function () {
    expect($this->policy->update($this->user, $this->org))->toBeFalse()
        ->and($this->policy->delete($this->user, $this->org))->toBeFalse();
});
