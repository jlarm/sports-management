<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;

function locationMemberLogin(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('index renders the locations inertia page ordered by name', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);
    Location::factory()->for($this->org)->create(['name' => 'Zeta Park']);
    Location::factory()->for($this->org)->create(['name' => 'Alpha Field']);
    Location::factory()->for($this->org)->create(['name' => 'Mason Stadium']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('locations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('locations/Index')
            ->has('locations', 3)
            ->where('locations.0.name', 'Alpha Field')
            ->where('locations.1.name', 'Mason Stadium')
            ->where('locations.2.name', 'Zeta Park')
        );
});

test('index is forbidden for users without a current organization', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('locations.index'))
        ->assertForbidden();
});

test('admin can create a location', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('locations.store'), [
            'name' => 'Main Park Field 1',
            'address' => '123 Main St',
            'maps_link' => 'https://maps.google.com/?q=main+park',
        ])
        ->assertRedirect(route('locations.index'));

    $location = Location::query()->withoutGlobalScopes()->firstOrFail();

    expect($location->name)->toBe('Main Park Field 1')
        ->and($location->organization_id)->toBe($this->org->id)
        ->and($location->address)->toBe('123 Main St')
        ->and($location->maps_link)->toBe('https://maps.google.com/?q=main+park');
});

test('coach cannot create a location', function () {
    $coach = locationMemberLogin($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('locations.store'), ['name' => 'Main Field'])
        ->assertForbidden();
});

test('store rejects duplicate names within the same organization', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);
    Location::factory()->for($this->org)->create(['name' => 'Main Field']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('locations.index'))
        ->post(route('locations.store'), ['name' => 'Main Field'])
        ->assertRedirect(route('locations.index'))
        ->assertSessionHasErrors('name');
});

test('store rejects malformed maps_link', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('locations.index'))
        ->post(route('locations.store'), [
            'name' => 'Main Field',
            'maps_link' => 'not-a-url',
        ])
        ->assertSessionHasErrors('maps_link');
});

test('store accepts a null address and maps_link', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('locations.store'), ['name' => 'Bare Bones Park'])
        ->assertRedirect(route('locations.index'));

    $location = Location::query()->withoutGlobalScopes()->firstOrFail();

    expect($location->address)->toBeNull()
        ->and($location->maps_link)->toBeNull();
});

test('admin can update a location', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);
    $location = Location::factory()->for($this->org)->create(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('locations.update', $location), [
            'name' => 'New Name',
            'address' => '456 Side St',
        ])
        ->assertRedirect(route('locations.index'));

    $fresh = $location->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->address)->toBe('456 Side St');
});

test('admin can archive a location via soft delete', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);
    $location = Location::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('locations.destroy', $location))
        ->assertRedirect(route('locations.index'));

    expect(Location::query()->withoutGlobalScopes()->withTrashed()->find($location->id)?->trashed())->toBeTrue();
});

test('locations from another organization 404 via route model binding', function () {
    $admin = locationMemberLogin($this->org, OrganizationRole::Admin);
    $otherLocation = Location::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('locations.update', $otherLocation), ['name' => 'Hijack'])
        ->assertNotFound();
});
