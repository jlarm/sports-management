<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('factory creates organization with owner', function () {
    $org = Organization::factory()->create();

    expect($org->name)->not->toBeEmpty()
        ->and($org->slug)->not->toBeEmpty()
        ->and($org->owner)->toBeInstanceOf(User::class);
});

test('user can belong to multiple organizations with different roles', function () {
    $user = User::factory()->create();
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $user->organizations()->attach($org1, ['role' => OrganizationRole::Owner->value]);
    $user->organizations()->attach($org2, ['role' => OrganizationRole::Coach->value]);

    expect($user->organizations()->count())->toBe(2)
        ->and($user->roleIn($org1))->toBe(OrganizationRole::Owner)
        ->and($user->roleIn($org2))->toBe(OrganizationRole::Coach)
        ->and($user->belongsToOrganization($org1))->toBeTrue()
        ->and($user->roleIn(Organization::factory()->create()))->toBeNull();
});

test('organization exposes its members and their roles', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create();

    $org->members()->attach($admin, ['role' => OrganizationRole::Admin->value]);

    expect($org->hasMember($admin))->toBeTrue()
        ->and($org->roleFor($admin))->toBe(OrganizationRole::Admin);
});

test('duplicate organization_user pivot rows are rejected', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $user->organizations()->attach($org, ['role' => OrganizationRole::Coach->value]);

    expect(fn () => $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
