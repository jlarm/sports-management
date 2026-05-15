<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Submission;
use App\Models\User;

function reviewAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->form = Form::factory()->for($this->org)->published()->create();
});

test('review page lists no candidates when none exist', function () {
    $admin = reviewAdminLogin($this->org);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['last_name' => 'NoMatch', 'dob' => '2014-04-12', 'parent_email' => 'p@p.com'],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.review', [$this->form, $submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/submissions/Review')
            ->where('match.can_match_player', true)
            ->where('match.can_match_guardian', true)
            ->has('match.player.candidates', 0)
            ->has('match.guardian.candidates', 0)
        );
});

test('review page exposes existing player and guardian candidates', function () {
    $admin = reviewAdminLogin($this->org);
    Player::factory()->for($this->org)->create([
        'last_name' => 'Lopez',
        'dob' => '2014-04-12',
    ]);
    Guardian::factory()->for($this->org)->create([
        'email' => 'parent@example.com',
    ]);

    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            'parent_email' => 'parent@example.com',
        ],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.review', [$this->form, $submission]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('match.player.candidates', 1)
            ->has('match.guardian.candidates', 1)
        );
});

test('coach cannot access the review page', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $submission = Submission::factory()->for($this->org)->for($this->form)->create();

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.review', [$this->form, $submission]))
        ->assertForbidden();
});

test('review 404s for a submission belonging to a different form', function () {
    $admin = reviewAdminLogin($this->org);
    $otherForm = Form::factory()->for($this->org)->create();
    $submission = Submission::factory()->for($this->org)->for($otherForm)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.submissions.review', [$this->form, $submission]))
        ->assertNotFound();
});
