<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->token = Invitation::mintToken();
    $this->invitation = Invitation::factory()->for($this->org)->create([
        'email' => 'invitee@example.com',
        'token_hash' => $this->token['hash'],
    ]);
});

test('decline stamps declined_at and does not attach a member', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('invitations.decline', $this->token['raw']))
        ->assertRedirect(route('home'));

    $this->invitation->refresh();

    expect($this->invitation->declined_at)->not->toBeNull()
        ->and($user->fresh()?->belongsToOrganization($this->org))->toBeFalse();
});

test('decline by a different email is forbidden', function () {
    $other = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($other)
        ->post(route('invitations.decline', $this->token['raw']))
        ->assertForbidden();
});

test('decline on an already declined invitation returns 410', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'email_verified_at' => now(),
    ]);

    $this->invitation->forceFill(['declined_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('invitations.decline', $this->token['raw']))
        ->assertStatus(410);
});
