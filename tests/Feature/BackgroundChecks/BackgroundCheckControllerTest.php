<?php

declare(strict_types=1);

use App\Enums\BackgroundCheckStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\BackgroundCheck;
use App\Models\Organization;
use App\Models\User;

function checksAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('index lists owners admins and coaches with their checks', function () {
    $admin = checksAdminLogin($this->org);
    $coach = User::factory()->create(['name' => 'Coach Sam']);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $guardian = User::factory()->create();
    $guardian->organizations()->attach($this->org, ['role' => OrganizationRole::Guardian->value]);
    BackgroundCheck::factory()->for($this->org)->for($coach)->cleared()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('background-checks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('background-checks/Index')
            ->has('rows', 2)
            ->where('rows', function ($rows) use ($coach) {
                $coachRow = collect($rows)->firstWhere('user.id', $coach->id);
                expect($coachRow['check']['is_current'])->toBeTrue()
                    ->and($coachRow['check']['status'])->toBe(BackgroundCheckStatus::Cleared->value);

                return true;
            })
        );
});

test('coach cannot view the background checks page', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('background-checks.index'))
        ->assertForbidden();
});

test('admin can record a check and an audit log entry is written', function () {
    $admin = checksAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('background-checks.store'), [
            'user_id' => $coach->id,
            'provider' => 'NCSI',
            'status' => BackgroundCheckStatus::Cleared->value,
            'cleared_through' => now()->addYear()->toDateString(),
        ])
        ->assertRedirectToRoute('background-checks.index');

    $check = BackgroundCheck::query()->firstOrFail();
    expect($check->user_id)->toBe($coach->id);
    expect(
        AuditLog::query()->withoutGlobalScopes()->where('action', 'background_check.created')->count()
    )->toBe(1);
});

test('store rejects a duplicate check for the same user in the same org', function () {
    $admin = checksAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    BackgroundCheck::factory()->for($this->org)->for($coach)->cleared()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('background-checks.index'))
        ->post(route('background-checks.store'), [
            'user_id' => $coach->id,
            'provider' => 'NCSI',
            'status' => BackgroundCheckStatus::Cleared->value,
        ])
        ->assertSessionHasErrors('user_id');
});

test('store rejects a user who is not a member of the current org', function () {
    $admin = checksAdminLogin($this->org);
    $stranger = User::factory()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('background-checks.index'))
        ->post(route('background-checks.store'), [
            'user_id' => $stranger->id,
            'provider' => 'NCSI',
            'status' => BackgroundCheckStatus::Cleared->value,
        ])
        ->assertSessionHasErrors('user_id');
});

test('admin can update an existing check', function () {
    $admin = checksAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $check = BackgroundCheck::factory()->for($this->org)->for($coach)->pending()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('background-checks.update', $check), [
            'provider' => 'Sterling',
            'status' => BackgroundCheckStatus::Cleared->value,
            'cleared_through' => now()->addYear()->toDateString(),
        ])
        ->assertRedirect();

    expect($check->fresh()?->provider)->toBe('Sterling')
        ->and($check->fresh()?->status)->toBe(BackgroundCheckStatus::Cleared);
});

test('admin can remove a check', function () {
    $admin = checksAdminLogin($this->org);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    $check = BackgroundCheck::factory()->for($this->org)->for($coach)->cleared()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('background-checks.destroy', $check))
        ->assertRedirect();

    expect(BackgroundCheck::query()->whereKey($check->id)->exists())->toBeFalse();
});

test('a check from another organization 404s on update', function () {
    $admin = checksAdminLogin($this->org);
    $other = Organization::factory()->create();
    $foreign = BackgroundCheck::factory()->for($other)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('background-checks.update', $foreign), [
            'provider' => 'X',
            'status' => BackgroundCheckStatus::Cleared->value,
        ])
        ->assertNotFound();
});
