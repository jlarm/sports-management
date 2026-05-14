<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Division;
use App\Models\Organization;
use App\Models\User;

function divisionMemberLogin(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('index renders the divisions inertia page ordered by display_order then name', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);
    Division::factory()->for($this->org)->create(['name' => 'Zeta', 'display_order' => 1]);
    Division::factory()->for($this->org)->create(['name' => 'Alpha', 'display_order' => 2]);
    Division::factory()->for($this->org)->create(['name' => 'Beta', 'display_order' => 1]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('divisions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('divisions/Index')
            ->has('divisions', 3)
            ->where('divisions.0.name', 'Beta')
            ->where('divisions.1.name', 'Zeta')
            ->where('divisions.2.name', 'Alpha')
        );
});

test('index is forbidden for users without a current organization', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('divisions.index'))
        ->assertForbidden();
});

test('admin can create a division', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('divisions.store'), ['name' => '10U', 'display_order' => 1])
        ->assertRedirect(route('divisions.index'));

    $division = Division::query()->withoutGlobalScopes()->firstOrFail();

    expect($division->name)->toBe('10U')
        ->and($division->organization_id)->toBe($this->org->id)
        ->and($division->display_order)->toBe(1);
});

test('coach cannot create a division', function () {
    $coach = divisionMemberLogin($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('divisions.store'), ['name' => '10U'])
        ->assertForbidden();
});

test('store rejects duplicate names within the same organization', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);
    Division::factory()->for($this->org)->create(['name' => '10U']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('divisions.index'))
        ->post(route('divisions.store'), ['name' => '10U'])
        ->assertRedirect(route('divisions.index'))
        ->assertSessionHasErrors('name');
});

test('store rejects negative display_order', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('divisions.index'))
        ->post(route('divisions.store'), ['name' => '10U', 'display_order' => -1])
        ->assertSessionHasErrors('display_order');
});

test('admin can update a division', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);
    $division = Division::factory()->for($this->org)->create(['name' => '10U']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('divisions.update', $division), ['name' => '11U', 'display_order' => 5])
        ->assertRedirect(route('divisions.index'));

    $fresh = $division->fresh();
    expect($fresh->name)->toBe('11U')
        ->and($fresh->display_order)->toBe(5);
});

test('update allows keeping the same name', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);
    $division = Division::factory()->for($this->org)->create(['name' => '10U']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('divisions.update', $division), ['name' => '10U', 'display_order' => 9])
        ->assertRedirect(route('divisions.index'));
});

test('admin can archive a division via soft delete', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);
    $division = Division::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('divisions.destroy', $division))
        ->assertRedirect(route('divisions.index'));

    expect(Division::query()->withoutGlobalScopes()->withTrashed()->find($division->id)?->trashed())->toBeTrue();
});

test('divisions from another organization 404 via route model binding', function () {
    $admin = divisionMemberLogin($this->org, OrganizationRole::Admin);
    $otherDivision = Division::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('divisions.update', $otherDivision), ['name' => 'Hijack'])
        ->assertNotFound();
});
