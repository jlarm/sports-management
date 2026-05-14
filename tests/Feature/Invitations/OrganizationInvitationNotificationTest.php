<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;

beforeEach(function () {
    $this->org = Organization::factory()->create(['name' => 'Cary Trojans']);
});

test('toMail names the inviter when present', function () {
    $inviter = User::factory()->create(['name' => 'Joe Lohr']);
    $invitation = Invitation::factory()->for($this->org)->create([
        'email' => 'coach@example.com',
        'role' => OrganizationRole::Coach->value,
        'invited_by_user_id' => $inviter->id,
    ]);

    $message = (new OrganizationInvitationNotification($invitation, 'token-abc'))
        ->toMail(new AnonymousNotifiable);

    expect($message->subject)->toBe("You've been invited to join Cary Trojans");

    $rendered = implode("\n", $message->introLines);
    expect($rendered)->toContain('Joe Lohr has invited you to join Cary Trojans');
});

test('toMail falls back to a generic line when no inviter is recorded', function () {
    $invitation = Invitation::factory()->for($this->org)->create([
        'email' => 'coach@example.com',
        'role' => OrganizationRole::Coach->value,
        'invited_by_user_id' => null,
    ]);

    $message = (new OrganizationInvitationNotification($invitation, 'token-abc'))
        ->toMail(new AnonymousNotifiable);

    $rendered = implode("\n", $message->introLines);
    expect($rendered)->toContain("You've been invited to join Cary Trojans");
});

test('toMail links to the accept route with the raw token', function () {
    $invitation = Invitation::factory()->for($this->org)->create();
    $token = 'raw-token-xyz';

    $message = (new OrganizationInvitationNotification($invitation, $token))
        ->toMail(new AnonymousNotifiable);

    expect($message->actionUrl)->toBe(route('invitations.show', ['token' => $token]));
});

test('via returns the mail channel', function () {
    $invitation = Invitation::factory()->for($this->org)->create();

    expect((new OrganizationInvitationNotification($invitation, 'token'))->via(new AnonymousNotifiable))
        ->toBe(['mail']);
});
