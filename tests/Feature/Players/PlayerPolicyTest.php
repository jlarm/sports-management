<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Player;
use App\Models\User;
use App\Policies\PlayerPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(PlayerPolicy::class);
    $this->player = Player::factory()->for($this->org)->create();
});

function playerMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny and view require membership in the current organization', function () {
    $member = playerMember($this->org, OrganizationRole::Coach);
    $stranger = User::factory()->create();

    expect($this->policy->viewAny($member))->toBeTrue()
        ->and($this->policy->viewAny($stranger))->toBeFalse()
        ->and($this->policy->view($member, $this->player))->toBeTrue();
});

test('view rejects players belonging to a different organization', function () {
    $member = playerMember($this->org, OrganizationRole::Coach);
    $otherPlayer = Player::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->view($member, $otherPlayer))->toBeFalse();
});

test('create, update, delete, restore require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = playerMember($this->org, $role);

    expect($this->policy->create($user))->toBe($expected)
        ->and($this->policy->update($user, $this->player))->toBe($expected)
        ->and($this->policy->delete($user, $this->player))->toBe($expected)
        ->and($this->policy->restore($user, $this->player))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('management policies fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = playerMember($this->org, OrganizationRole::Admin);

    expect($this->policy->create($admin))->toBeFalse()
        ->and($this->policy->update($admin, $this->player))->toBeFalse();
});

test('management policies reject players from a different organization', function () {
    $admin = playerMember($this->org, OrganizationRole::Admin);
    $otherPlayer = Player::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->update($admin, $otherPlayer))->toBeFalse()
        ->and($this->policy->delete($admin, $otherPlayer))->toBeFalse();
});
