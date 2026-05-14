<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;

function submissionMemberLogin(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->form = Form::factory()->for($this->org)->published()->create();
});

test('admin can list submissions for a form, including the submitter when present', function () {
    $admin = submissionMemberLogin($this->org, OrganizationRole::Admin);
    $submitter = User::factory()->create(['name' => 'Parent Adams', 'email' => 'parent@example.com']);
    Submission::factory()->for($this->org)->for($this->form)->create([
        'submitted_by_user_id' => $submitter->id,
    ]);
    Submission::factory()->for($this->org)->for($this->form)->create();
    Submission::factory()->for($this->org)->for(
        Form::factory()->for($this->org)->create()
    )->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.index', $this->form))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/submissions/Index')
            ->where('form.id', $this->form->id)
            ->has('submissions', 2)
            ->where('submissions', function ($submissions) use ($submitter) {
                $named = collect($submissions)->first(
                    fn ($row) => is_array($row['submitted_by'] ?? null)
                );
                expect($named['submitted_by']['name'] ?? null)->toBe($submitter->name)
                    ->and($named['submitted_by']['email'] ?? null)->toBe($submitter->email);

                return true;
            })
        );
});

test('submission show includes the submitter when one is attached', function () {
    $admin = submissionMemberLogin($this->org, OrganizationRole::Admin);
    $submitter = User::factory()->create(['name' => 'Parent Adams', 'email' => 'parent@example.com']);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'submitted_by_user_id' => $submitter->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.show', [$this->form, $submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('submission.submitted_by.name', $submitter->name)
            ->where('submission.submitted_by.email', $submitter->email)
        );
});

test('coach cannot view submissions index', function () {
    $coach = submissionMemberLogin($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.index', $this->form))
        ->assertForbidden();
});

test('admin can view a single submission', function () {
    $admin = submissionMemberLogin($this->org, OrganizationRole::Admin);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['first_name' => 'Diego'],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.show', [$this->form, $submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/submissions/Show')
            ->where('submission.id', $submission->id)
            ->where('submission.data.first_name', 'Diego')
            ->where('submission.is_outdated', false)
        );
});

test('submission show flags older schema versions', function () {
    $admin = submissionMemberLogin($this->org, OrganizationRole::Admin);
    $this->form->forceFill(['schema_version' => 3])->save();

    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'schema_version' => 1,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.show', [$this->form, $submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('submission.is_outdated', true)
            ->where('submission.schema_version', 1)
        );
});

test('a submission that belongs to a different form 404s on show', function () {
    $admin = submissionMemberLogin($this->org, OrganizationRole::Admin);
    $otherForm = Form::factory()->for($this->org)->create();
    $foreign = Submission::factory()->for($this->org)->for($otherForm)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.show', [$this->form, $foreign]))
        ->assertNotFound();
});

test('forms from another organization 404 on the submissions index', function () {
    $admin = submissionMemberLogin($this->org, OrganizationRole::Admin);
    $otherForm = Form::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.index', $otherForm))
        ->assertNotFound();
});
