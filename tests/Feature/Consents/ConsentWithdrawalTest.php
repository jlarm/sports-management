<?php

declare(strict_types=1);

use App\Enums\ConsentType;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;

function withdrawAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->form = Form::factory()->for($this->org)->published()->create();
    $this->submission = Submission::factory()->for($this->org)->for($this->form)->create();
    $this->consent = Consent::factory()->for($this->org)->for($this->submission)->create();
});

test('admin can withdraw a consent and an audit row is written', function () {
    $admin = withdrawAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.consents.withdraw', [$this->form, $this->submission, $this->consent]))
        ->assertRedirectToRoute('forms.submissions.show', [$this->form, $this->submission]);

    expect($this->consent->fresh()?->isWithdrawn())->toBeTrue()
        ->and($this->consent->fresh()?->withdrawn_by_user_id)->toBe($admin->id);

    expect(AuditLog::query()->withoutGlobalScopes()->where('action', 'consent.withdrawn')->count())->toBe(1);
});

test('withdrawing an already-withdrawn consent is forbidden by policy', function () {
    $admin = withdrawAdminLogin($this->org);
    $this->consent->forceFill(['withdrawn_at' => now()])->save();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.consents.withdraw', [$this->form, $this->submission, $this->consent]))
        ->assertForbidden();

    expect(AuditLog::query()->withoutGlobalScopes()->where('action', 'consent.withdrawn')->count())->toBe(0);
});

test('coach cannot withdraw a consent', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.consents.withdraw', [$this->form, $this->submission, $this->consent]))
        ->assertForbidden();
});

test('withdraw 404s when the consent does not belong to the submission', function () {
    $admin = withdrawAdminLogin($this->org);
    $other = Submission::factory()->for($this->org)->for($this->form)->create();
    $foreignConsent = Consent::factory()->for($this->org)->for($other)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.consents.withdraw', [$this->form, $this->submission, $foreignConsent]))
        ->assertNotFound();
});

test('withdraw 404s when the submission does not belong to the form', function () {
    $admin = withdrawAdminLogin($this->org);
    $otherForm = Form::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.consents.withdraw', [$otherForm, $this->submission, $this->consent]))
        ->assertNotFound();
});

test('show page exposes consents and reflects the withdrawn state', function () {
    $admin = withdrawAdminLogin($this->org);
    $this->consent->forceFill(['withdrawn_at' => now(), 'withdrawn_by_user_id' => $admin->id])->save();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.show', [$this->form, $this->submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('submission.consents', 1)
            ->where('submission.consents.0.is_withdrawn', true)
            ->where('submission.consents.0.type', ConsentType::Registration->value)
            ->where('submission.consents.0.withdrawn_by.name', $admin->name)
        );
});
