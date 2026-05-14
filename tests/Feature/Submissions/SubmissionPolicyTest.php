<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(SubmissionPolicy::class);
    $this->form = Form::factory()->for($this->org)->create();
    $this->submission = Submission::factory()->for($this->org)->for($this->form)->create();
});

function submissionMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny requires owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = submissionMember($this->org, $role);

    expect($this->policy->viewAny($user))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('view and delete reject submissions from another organization', function () {
    $admin = submissionMember($this->org, OrganizationRole::Admin);
    $otherSubmission = Submission::factory()
        ->for($otherOrg = Organization::factory()->create())
        ->for(Form::factory()->for($otherOrg)->create())
        ->create();

    expect($this->policy->view($admin, $this->submission))->toBeTrue()
        ->and($this->policy->view($admin, $otherSubmission))->toBeFalse()
        ->and($this->policy->delete($admin, $this->submission))->toBeTrue()
        ->and($this->policy->delete($admin, $otherSubmission))->toBeFalse();
});

test('every check fails closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = submissionMember($this->org, OrganizationRole::Admin);

    expect($this->policy->viewAny($admin))->toBeFalse()
        ->and($this->policy->view($admin, $this->submission))->toBeFalse()
        ->and($this->policy->delete($admin, $this->submission))->toBeFalse();
});
