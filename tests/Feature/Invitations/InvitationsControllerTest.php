<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

function invMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    Notification::fake();
    $this->org = Organization::factory()->create();
});

test('index renders the invitations inertia page with the orgs invitations', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);
    Invitation::factory()->for($this->org)->create([
        'email' => 'invitee@example.com',
        'invited_by_user_id' => $admin->id,
    ]);
    Invitation::factory()->for($this->org)->create([
        'email' => 'orphan@example.com',
    ]);
    Invitation::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('invitations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invitations/Index')
            ->has('invitations', 2)
            ->where('organizationName', $this->org->name)
            ->where('invitations', function ($invitations) use ($admin) {
                $byEmail = collect($invitations)->keyBy('email');

                expect($byEmail['invitee@example.com']['invited_by'])->toBe($admin->name)
                    ->and($byEmail['orphan@example.com']['invited_by'])->toBeNull();

                return true;
            })
        );
});

test('coach cannot view invitations index', function () {
    $coach = invMember($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('invitations.index'))
        ->assertForbidden();
});

test('admin can send an invitation', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.store'), [
            'email' => 'coach@example.com',
            'role' => OrganizationRole::Coach->value,
        ])
        ->assertRedirect(route('invitations.index'));

    $invitation = Invitation::query()->withoutGlobalScopes()->firstOrFail();

    expect($invitation->email)->toBe('coach@example.com')
        ->and($invitation->role)->toBe(OrganizationRole::Coach)
        ->and($invitation->invited_by_user_id)->toBe($admin->id)
        ->and($invitation->organization_id)->toBe($this->org->id)
        ->and($invitation->expires_at->isFuture())->toBeTrue();

    Notification::assertSentOnDemand(
        OrganizationInvitationNotification::class,
        fn (OrganizationInvitationNotification $n, array $channels, $notifiable) => true,
    );
});

test('coach cannot send an invitation', function () {
    $coach = invMember($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.store'), [
            'email' => 'someone@example.com',
            'role' => OrganizationRole::Coach->value,
        ])
        ->assertForbidden();
});

test('store rejects assigning the Owner role', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('invitations.index'))
        ->post(route('invitations.store'), [
            'email' => 'usurper@example.com',
            'role' => OrganizationRole::Owner->value,
        ])
        ->assertSessionHasErrors('role');
});

test('store rejects inviting an existing member', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);
    $existing = invMember($this->org, OrganizationRole::Coach);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('invitations.index'))
        ->post(route('invitations.store'), [
            'email' => $existing->email,
            'role' => OrganizationRole::Coach->value,
        ])
        ->assertSessionHasErrors('email');
});

test('store rejects when a pending invitation already exists', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);
    Invitation::factory()->for($this->org)->create(['email' => 'dupe@example.com']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('invitations.index'))
        ->post(route('invitations.store'), [
            'email' => 'dupe@example.com',
            'role' => OrganizationRole::Coach->value,
        ])
        ->assertSessionHasErrors('email');
});

test('store allows re-inviting after a previous invitation was revoked', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);
    Invitation::factory()->for($this->org)->revoked()->create(['email' => 'again@example.com']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.store'), [
            'email' => 'again@example.com',
            'role' => OrganizationRole::Coach->value,
        ])
        ->assertRedirect(route('invitations.index'));

    expect(Invitation::query()->withoutGlobalScopes()->pending()->count())->toBe(1);
});

test('store rejects malformed email and uppercase domain', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('invitations.index'))
        ->post(route('invitations.store'), [
            'email' => 'NotAnEmail',
            'role' => OrganizationRole::Coach->value,
        ])
        ->assertSessionHasErrors('email');
});

test('admin can revoke a pending invitation', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);
    $invitation = Invitation::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect(route('invitations.index'));

    expect($invitation->fresh()?->revoked_at)->not->toBeNull();
});

test('revoking a non-pending invitation is a no-op', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);
    $invitation = Invitation::factory()->for($this->org)->accepted()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect(route('invitations.index'));

    expect($invitation->fresh()?->revoked_at)->toBeNull();
});

test('invitations from another organization 404 via route binding', function () {
    $admin = invMember($this->org, OrganizationRole::Admin);
    $otherInvitation = Invitation::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('invitations.destroy', $otherInvitation))
        ->assertNotFound();
});
