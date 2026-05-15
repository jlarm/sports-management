<?php

declare(strict_types=1);

use App\Enums\MatchAction;
use App\Enums\OrganizationRole;
use App\Enums\SubmissionStatus;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Submission;
use App\Models\SubmissionDecision;
use App\Models\User;

function processAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->form = Form::factory()->for($this->org)->published()->create();
});

test('process creates a new player and guardian and links them', function () {
    $admin = processAdminLogin($this->org);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            'parent_email' => 'parent@example.com',
        ],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Created->value,
            'player' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12', 'jersey_size' => 'YM'],
            'guardian_action' => MatchAction::Created->value,
            'guardian' => ['first_name' => 'Maria', 'last_name' => 'Lopez', 'email' => 'maria@example.com', 'phone' => '555'],
        ])
        ->assertRedirectToRoute('forms.submissions.show', [$this->form, $submission]);

    $player = Player::query()->firstOrFail();
    $guardian = Guardian::query()->firstOrFail();
    expect($player->first_name)->toBe('Diego')
        ->and($player->jersey_size)->toBe('YM')
        ->and($guardian->email)->toBe('maria@example.com')
        ->and($player->guardians()->whereKey($guardian->id)->exists())->toBeTrue()
        ->and($submission->fresh()?->status)->toBe(SubmissionStatus::Processed);

    $decision = SubmissionDecision::query()->firstOrFail();
    expect($decision->player_action)->toBe(MatchAction::Created)
        ->and($decision->guardian_action)->toBe(MatchAction::Created)
        ->and($decision->player_id)->toBe($player->id)
        ->and($decision->guardian_id)->toBe($guardian->id)
        ->and($decision->decided_by_user_id)->toBe($admin->id);
});

test('process can merge with an existing player and guardian', function () {
    $admin = processAdminLogin($this->org);
    $existingPlayer = Player::factory()->for($this->org)->create([
        'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
        'jersey_size' => null, 'medical_notes' => null,
    ]);
    $existingGuardian = Guardian::factory()->for($this->org)->create([
        'email' => 'parent@example.com',
    ]);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Merged->value,
            'player_id' => $existingPlayer->id,
            'player' => ['jersey_size' => 'YL', 'medical_notes' => 'updated note'],
            'guardian_action' => MatchAction::Merged->value,
            'guardian_id' => $existingGuardian->id,
            'guardian' => ['phone' => '999'],
        ])
        ->assertRedirect();

    expect($existingPlayer->fresh()?->jersey_size)->toBe('YL')
        ->and($existingPlayer->fresh()?->medical_notes)->toBe('updated note')
        ->and($existingGuardian->fresh()?->phone)->toBe('999')
        ->and(Player::query()->count())->toBe(1)
        ->and(Guardian::query()->count())->toBe(1);
});

test('process marks submission as Skipped when both axes are skipped', function () {
    $admin = processAdminLogin($this->org);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Skipped->value,
            'guardian_action' => MatchAction::Skipped->value,
            'notes' => 'duplicate / test entry',
        ])
        ->assertRedirect();

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Skipped);

    $decision = SubmissionDecision::query()->firstOrFail();
    expect($decision->notes)->toBe('duplicate / test entry');
});

test('process supports force_created which creates a player even when matches exist', function () {
    $admin = processAdminLogin($this->org);
    Player::factory()->for($this->org)->create([
        'first_name' => 'Existing', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
    ]);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::ForceCreated->value,
            'player' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
            'guardian_action' => MatchAction::Skipped->value,
        ])
        ->assertRedirect();

    expect(Player::query()->count())->toBe(2);
    $decision = SubmissionDecision::query()->firstOrFail();
    expect($decision->player_action)->toBe(MatchAction::ForceCreated);
});

test('coach cannot process a submission', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Skipped->value,
            'guardian_action' => MatchAction::Skipped->value,
        ])
        ->assertForbidden();
});

test('process 404s when the submission belongs to a different form', function () {
    $admin = processAdminLogin($this->org);
    $otherForm = Form::factory()->for($this->org)->create();
    $submission = Submission::factory()->for($this->org)->for($otherForm)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Skipped->value,
            'guardian_action' => MatchAction::Skipped->value,
        ])
        ->assertNotFound();
});

test('merging player rejects an id from another organization', function () {
    $admin = processAdminLogin($this->org);
    $other = Organization::factory()->create();
    $foreignPlayer = Player::factory()->for($other)->create();
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('forms.submissions.review', [$this->form, $submission]))
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Merged->value,
            'player_id' => $foreignPlayer->id,
            'guardian_action' => MatchAction::Skipped->value,
        ])
        ->assertSessionHasErrors('player_id');
});

test('show page returns decisions history once the submission is processed', function () {
    $admin = processAdminLogin($this->org);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Skipped->value,
            'guardian_action' => MatchAction::Skipped->value,
            'notes' => 'reviewed in test',
        ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.show', [$this->form, $submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('submission.status', SubmissionStatus::Skipped->value)
            ->has('submission.decisions', 1)
            ->where('submission.decisions.0.notes', 'reviewed in test')
            ->where('submission.decisions.0.decided_by.name', $admin->name)
        );
});

test('show page exposes linked player and guardian after a Created decision', function () {
    $admin = processAdminLogin($this->org);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Created->value,
            'player' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
            'guardian_action' => MatchAction::Created->value,
            'guardian' => ['first_name' => 'Maria', 'last_name' => 'Lopez', 'email' => 'maria@example.com'],
        ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.show', [$this->form, $submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('submission.decisions.0.player.first_name', 'Diego')
            ->where('submission.decisions.0.player.last_name', 'Lopez')
            ->where('submission.decisions.0.guardian.email', 'maria@example.com')
        );

    $decision = SubmissionDecision::query()->firstOrFail();
    expect($decision->submission?->id)->toBe($submission->id);
});

test('guardian user and players relations resolve from the model', function () {
    $admin = processAdminLogin($this->org);
    $user = User::factory()->create();
    $guardian = Guardian::factory()->for($this->org)->create(['user_id' => $user->id]);
    $player = Player::factory()->for($this->org)->create();
    $player->guardians()->attach($guardian->id);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.index', $this->form))->assertOk();

    expect($guardian->fresh()?->user?->id)->toBe($user->id)
        ->and($guardian->fresh()?->players()->whereKey($player->id)->exists())->toBeTrue();
});

test('player_guardian pivot relations resolve to their models', function () {
    $admin = processAdminLogin($this->org);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$this->form, $submission]), [
            'player_action' => MatchAction::Created->value,
            'player' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
            'guardian_action' => MatchAction::Created->value,
            'guardian' => ['first_name' => 'Maria', 'last_name' => 'Lopez', 'email' => 'maria@example.com'],
        ]);

    $player = Player::query()->firstOrFail();
    $pivot = $player->guardians()->first()?->pivot;

    expect($pivot)->not()->toBeNull()
        ->and($pivot?->player?->id)->toBe($player->id)
        ->and($pivot?->guardian?->id)->toBe($player->guardians()->first()?->id);
});
