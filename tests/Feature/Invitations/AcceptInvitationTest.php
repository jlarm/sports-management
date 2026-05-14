<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->token = Invitation::mintToken();
    $this->invitation = Invitation::factory()->for($this->org)->create([
        'email' => 'invitee@example.com',
        'role' => OrganizationRole::Admin->value,
        'token_hash' => $this->token['hash'],
    ]);
});

test('show page renders for the invited user', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('invitations.show', $this->token['raw']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invitations/Show')
            ->where('invitation.organization_name', $this->org->name)
            ->where('invitation.role', OrganizationRole::Admin->value)
            ->where('invitation.email', 'invitee@example.com')
            ->where('invitation.email_matches', true)
            ->where('invitation.status', 'pending')
            ->where('token', $this->token['raw'])
        );
});

test('show page flags an email mismatch but still renders', function () {
    $other = User::factory()->create([
        'email' => 'someoneelse@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($other)
        ->get(route('invitations.show', $this->token['raw']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invitation.email_matches', false)
        );
});

test('show returns 404 for an unknown token', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('invitations.show', 'definitely-not-a-token'))
        ->assertNotFound();
});

test('accept attaches the user to the org and stamps accepted_at', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token['raw']))
        ->assertRedirect(route('dashboard'));

    $this->invitation->refresh();
    $user->refresh();

    expect($this->invitation->accepted_at)->not->toBeNull()
        ->and($user->belongsToOrganization($this->org))->toBeTrue()
        ->and($user->roleIn($this->org))->toBe(OrganizationRole::Admin);
});

test('accept stores the joined org as the current org in the session', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token['raw']))
        ->assertSessionHas('current_org_id', $this->org->id);
});

test('accept by a different email is forbidden', function () {
    $other = User::factory()->create([
        'email' => 'someoneelse@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($other)
        ->post(route('invitations.accept', $this->token['raw']))
        ->assertForbidden();
});

test('accept on an already accepted invitation returns 410', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->invitation->forceFill(['accepted_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token['raw']))
        ->assertStatus(410);
});

test('accept on a revoked invitation returns 410', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->invitation->forceFill(['revoked_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token['raw']))
        ->assertStatus(410);
});

test('accept on an expired invitation returns 410', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->invitation->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token['raw']))
        ->assertStatus(410);
});

test('guests are redirected to login when visiting an invitation', function () {
    $this->get(route('invitations.show', $this->token['raw']))
        ->assertRedirect(route('login'));
});
