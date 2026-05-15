<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function resendAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->invitation = Invitation::factory()->for($this->org)->create([
        'email' => 'invitee@example.com',
        'role' => OrganizationRole::Admin->value,
        'expires_at' => now()->addDays(2),
    ]);
});

test('admin can resend a pending invitation and the token is rotated', function () {
    Notification::fake();
    $admin = resendAdminLogin($this->org);
    $originalHash = $this->invitation->token_hash;

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.resend', $this->invitation))
        ->assertRedirectToRoute('invitations.index');

    expect($this->invitation->fresh()?->token_hash)->not()->toBe($originalHash);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        OrganizationInvitationNotification::class,
        function (OrganizationInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notifiable->routes['mail'] === 'invitee@example.com';
        }
    );

    expect(
        AuditLog::query()->withoutGlobalScopes()->where('action', 'invitation.resent')->count()
    )->toBe(1);
});

test('resending a revoked invitation is rejected with 422', function () {
    Notification::fake();
    $admin = resendAdminLogin($this->org);
    $this->invitation->forceFill(['revoked_at' => now()])->save();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.resend', $this->invitation))
        ->assertStatus(422);

    Notification::assertNothingSent();
});

test('resending an accepted invitation is rejected with 422', function () {
    Notification::fake();
    $admin = resendAdminLogin($this->org);
    $this->invitation->forceFill(['accepted_at' => now()])->save();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.resend', $this->invitation))
        ->assertStatus(422);
});

test('a coach cannot resend invitations', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.resend', $this->invitation))
        ->assertForbidden();
});

test('an invitation from another organization 404s', function () {
    $admin = resendAdminLogin($this->org);
    $other = Organization::factory()->create();
    $foreign = Invitation::factory()->for($other)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.resend', $foreign))
        ->assertNotFound();
});

test('resend pushes expires_at out to seven days from now', function () {
    Notification::fake();
    $admin = resendAdminLogin($this->org);
    $this->invitation->forceFill(['expires_at' => now()->addHour()])->save();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.resend', $this->invitation))
        ->assertRedirect();

    expect($this->invitation->fresh()?->expires_at?->greaterThan(now()->addDays(6)))->toBeTrue();
});

test('an expired invitation cannot be resent', function () {
    Notification::fake();
    $admin = resendAdminLogin($this->org);
    $this->invitation->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.resend', $this->invitation))
        ->assertStatus(422);

    Notification::assertNothingSent();
});
