<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Form;
use App\Models\Organization;
use App\Models\User;
use App\Policies\FormPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(FormPolicy::class);
    $this->form = Form::factory()->for($this->org)->create();
});

function formMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny + view require membership in the current organization', function () {
    $member = formMember($this->org, OrganizationRole::Coach);
    $stranger = User::factory()->create();

    expect($this->policy->viewAny($member))->toBeTrue()
        ->and($this->policy->view($member, $this->form))->toBeTrue()
        ->and($this->policy->viewAny($stranger))->toBeFalse();
});

test('view rejects forms from another organization', function () {
    $member = formMember($this->org, OrganizationRole::Coach);
    $otherForm = Form::factory()->for(Organization::factory()->create())->create();

    expect($this->policy->view($member, $otherForm))->toBeFalse();
});

test('create / update / delete / restore / publish / close require owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = formMember($this->org, $role);

    expect($this->policy->create($user))->toBe($expected)
        ->and($this->policy->update($user, $this->form))->toBe($expected)
        ->and($this->policy->delete($user, $this->form))->toBe($expected)
        ->and($this->policy->restore($user, $this->form))->toBe($expected)
        ->and($this->policy->publish($user, $this->form))->toBe($expected)
        ->and($this->policy->close($user, $this->form))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('management policies fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = formMember($this->org, OrganizationRole::Admin);

    expect($this->policy->create($admin))->toBeFalse()
        ->and($this->policy->update($admin, $this->form))->toBeFalse();
});
