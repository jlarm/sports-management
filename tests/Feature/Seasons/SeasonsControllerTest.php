<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Season;
use App\Models\User;

function asMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('index renders the seasons inertia page with the orgs seasons', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);
    Season::factory()->for($this->org)->count(2)->create();

    $created = Season::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->count();
    expect($created)->toBe(2);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('seasons.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/seasons/Index')
            ->has('seasons', 2)
        );
});

test('index is forbidden for users without a current organization', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('seasons.index'))
        ->assertForbidden();
});

test('admin can create a season', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.store'), [
            'name' => 'Spring 26',
            'start_date' => '2026-03-01',
            'end_date' => '2026-05-31',
            'is_registration_open' => true,
        ])
        ->assertRedirect(route('seasons.index'));

    $season = Season::query()->withoutGlobalScopes()->firstOrFail();

    expect($season->name)->toBe('Spring 26')
        ->and($season->organization_id)->toBe($this->org->id)
        ->and($season->is_registration_open)->toBeTrue()
        ->and($season->is_active)->toBeFalse();
});

test('coach cannot create a season', function () {
    $coach = asMember($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.store'), [
            'name' => 'Spring 26',
            'start_date' => '2026-03-01',
            'end_date' => '2026-05-31',
        ])
        ->assertForbidden();
});

test('store rejects duplicate names within the same organization', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);
    Season::factory()->for($this->org)->create(['name' => 'Spring 26']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('seasons.index'))
        ->post(route('seasons.store'), [
            'name' => 'Spring 26',
            'start_date' => '2026-03-01',
            'end_date' => '2026-05-31',
        ])
        ->assertRedirect(route('seasons.index'))
        ->assertSessionHasErrors('name');
});

test('store rejects end_date before start_date', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('seasons.index'))
        ->post(route('seasons.store'), [
            'name' => 'Bad',
            'start_date' => '2026-05-01',
            'end_date' => '2026-03-01',
        ])
        ->assertSessionHasErrors('end_date');
});

test('admin can update a season', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);
    $season = Season::factory()->for($this->org)->create(['name' => 'Original']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('seasons.update', $season), [
            'name' => 'Updated',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
        ])
        ->assertRedirect(route('seasons.index'));

    expect($season->fresh()->name)->toBe('Updated');
});

test('admin can archive a season via soft delete', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);
    $season = Season::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('seasons.destroy', $season))
        ->assertRedirect(route('seasons.index'));

    expect(Season::query()->withoutGlobalScopes()->withTrashed()->find($season->id)?->trashed())->toBeTrue();
});

test('activating a season deactivates the previously active one', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);
    $active = Season::factory()->for($this->org)->active()->create(['name' => 'Old']);
    $next = Season::factory()->for($this->org)->create(['name' => 'New']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.activate', $next))
        ->assertRedirect(route('seasons.index'));

    expect($active->fresh()->is_active)->toBeFalse()
        ->and($next->fresh()->is_active)->toBeTrue();
});

test('seasons from another organization 404 via route model binding', function () {
    $admin = asMember($this->org, OrganizationRole::Admin);
    $otherSeason = Season::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('seasons.update', $otherSeason), [
            'name' => 'Hijack',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
        ])
        ->assertNotFound();
});
