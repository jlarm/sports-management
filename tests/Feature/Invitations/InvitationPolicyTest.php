<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Policies\InvitationPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(InvitationPolicy::class);
    $this->invitation = Invitation::factory()->for($this->org)->create();
});

function invitationMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny and create require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = invitationMember($this->org, $role);

    expect($this->policy->viewAny($user))->toBe($expected)
        ->and($this->policy->create($user))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('view and delete require the invitation to belong to the current organization', function () {
    $admin = invitationMember($this->org, OrganizationRole::Admin);
    $otherInvitation = Invitation::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->view($admin, $this->invitation))->toBeTrue()
        ->and($this->policy->view($admin, $otherInvitation))->toBeFalse()
        ->and($this->policy->delete($admin, $this->invitation))->toBeTrue()
        ->and($this->policy->delete($admin, $otherInvitation))->toBeFalse();
});

test('all checks fail when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = invitationMember($this->org, OrganizationRole::Admin);

    expect($this->policy->viewAny($admin))->toBeFalse()
        ->and($this->policy->create($admin))->toBeFalse()
        ->and($this->policy->view($admin, $this->invitation))->toBeFalse()
        ->and($this->policy->delete($admin, $this->invitation))->toBeFalse();
});

test('non-members cannot view or manage invitations', function () {
    $stranger = User::factory()->create();

    expect($this->policy->viewAny($stranger))->toBeFalse()
        ->and($this->policy->create($stranger))->toBeFalse();
});
