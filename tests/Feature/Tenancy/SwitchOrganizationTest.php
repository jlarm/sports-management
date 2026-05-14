<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('member can switch the current organization', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user->organizations()->attach($orgA, ['role' => OrganizationRole::Owner->value]);
    $user->organizations()->attach($orgB, ['role' => OrganizationRole::Admin->value]);

    $this->actingAs($user)
        ->withSession(['current_org_id' => $orgA->id])
        ->from(route('home'))
        ->post(route('organizations.switch', $orgB))
        ->assertRedirect(route('home'))
        ->assertSessionHas('current_org_id', $orgB->id);
});

test('non-member receives 403 when switching to another organization', function () {
    $user = User::factory()->create();
    $otherOrg = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.switch', $otherOrg))
        ->assertForbidden();
});

test('guest cannot switch organization', function () {
    $org = Organization::factory()->create();

    $this->post(route('organizations.switch', $org))
        ->assertRedirect(route('login'));
});
