<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Consent;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;
use App\Policies\ConsentPolicy;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->policy = app(ConsentPolicy::class);
    $form = Form::factory()->for($this->org)->create();
    $submission = Submission::factory()->for($this->org)->for($form)->create();
    $this->consent = Consent::factory()->for($this->org)->for($submission)->create();
});

function consentMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

test('viewAny requires owner or admin role', function (OrganizationRole $role, bool $expected) {
    $user = consentMember($this->org, $role);

    expect($this->policy->viewAny($user))->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);

test('view rejects consents from another organization', function () {
    $admin = consentMember($this->org, OrganizationRole::Admin);
    $other = Organization::factory()->create();
    $otherForm = Form::factory()->for($other)->create();
    $otherSubmission = Submission::factory()->for($other)->for($otherForm)->create();
    $otherConsent = Consent::factory()->for($other)->for($otherSubmission)->create();

    expect($this->policy->view($admin, $this->consent))->toBeTrue()
        ->and($this->policy->view($admin, $otherConsent))->toBeFalse();
});

test('withdraw is denied when the consent is already withdrawn', function () {
    $admin = consentMember($this->org, OrganizationRole::Admin);
    $this->consent->forceFill(['withdrawn_at' => now()])->save();

    expect($this->policy->withdraw($admin, $this->consent->fresh()))->toBeFalse();
});

test('Consent relations resolve to their models', function () {
    $admin = consentMember($this->org, OrganizationRole::Admin);
    $player = App\Models\Player::factory()->for($this->org)->create();
    $guardian = App\Models\Guardian::factory()->for($this->org)->create();
    $this->consent->forceFill([
        'player_id' => $player->id,
        'guardian_id' => $guardian->id,
        'withdrawn_at' => now(),
        'withdrawn_by_user_id' => $admin->id,
    ])->save();

    $fresh = $this->consent->fresh();
    expect($fresh?->player?->id)->toBe($player->id)
        ->and($fresh?->guardian?->id)->toBe($guardian->id)
        ->and($fresh?->withdrawnBy?->id)->toBe($admin->id);
});

test('checks fail closed when tenant is unresolved', function () {
    $this->tenant->clear();
    $admin = consentMember($this->org, OrganizationRole::Admin);

    expect($this->policy->viewAny($admin))->toBeFalse()
        ->and($this->policy->view($admin, $this->consent))->toBeFalse()
        ->and($this->policy->withdraw($admin, $this->consent))->toBeFalse();
});
