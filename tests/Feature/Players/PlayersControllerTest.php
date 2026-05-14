<?php

declare(strict_types=1);

use App\Enums\BattingHand;
use App\Enums\OrganizationRole;
use App\Enums\ThrowingHand;
use App\Models\Organization;
use App\Models\Player;
use App\Models\User;

function playerMemberLogin(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('index renders the players inertia page ordered by last_name then first_name', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);
    Player::factory()->for($this->org)->create(['first_name' => 'Charlie', 'last_name' => 'Smith']);
    Player::factory()->for($this->org)->create(['first_name' => 'Anna', 'last_name' => 'Adams']);
    Player::factory()->for($this->org)->create(['first_name' => 'Brian', 'last_name' => 'Smith']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('players.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('players/Index')
            ->has('players', 3)
            ->where('players.0.last_name', 'Adams')
            ->where('players.1.first_name', 'Brian')
            ->where('players.2.first_name', 'Charlie')
        );
});

test('index is forbidden for users without a current organization', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('players.index'))
        ->assertForbidden();
});

test('admin can create a player with all fields', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('players.store'), [
            'first_name' => 'Johnny',
            'last_name' => 'Lopez',
            'dob' => '2014-04-12',
            'graduation_year' => 2032,
            'gender' => 'M',
            'bats' => BattingHand::Switch->value,
            'throws' => ThrowingHand::Right->value,
            'school' => 'Cary Elementary',
            'jersey_size' => 'YM',
            'medical_notes' => 'Mild peanut allergy',
            'external_id' => 'CRY-001',
            'notes' => 'Plays catcher',
        ])
        ->assertRedirect(route('players.index'));

    $player = Player::query()->withoutGlobalScopes()->firstOrFail();

    expect($player->first_name)->toBe('Johnny')
        ->and($player->last_name)->toBe('Lopez')
        ->and($player->organization_id)->toBe($this->org->id)
        ->and($player->bats)->toBe(BattingHand::Switch)
        ->and($player->throws)->toBe(ThrowingHand::Right)
        ->and($player->external_id)->toBe('CRY-001');
});

test('coach cannot create a player', function () {
    $coach = playerMemberLogin($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('players.store'), [
            'first_name' => 'Johnny',
            'last_name' => 'Lopez',
            'dob' => '2014-04-12',
        ])
        ->assertForbidden();
});

test('store rejects dob in the future', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('players.index'))
        ->post(route('players.store'), [
            'first_name' => 'Tomorrow',
            'last_name' => 'Baby',
            'dob' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasErrors('dob');
});

test('store rejects invalid handedness enums', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('players.index'))
        ->post(route('players.store'), [
            'first_name' => 'Bad',
            'last_name' => 'Hand',
            'dob' => '2014-04-12',
            'bats' => 'X',
            'throws' => 'Z',
        ])
        ->assertSessionHasErrors(['bats', 'throws']);
});

test('store rejects duplicate external_id within the same organization', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);
    Player::factory()->for($this->org)->create(['external_id' => 'CRY-1']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('players.index'))
        ->post(route('players.store'), [
            'first_name' => 'Dupe',
            'last_name' => 'Player',
            'dob' => '2014-04-12',
            'external_id' => 'CRY-1',
        ])
        ->assertSessionHasErrors('external_id');
});

test('admin can update a player', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create(['first_name' => 'Old']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('players.update', $player), [
            'first_name' => 'New',
            'last_name' => $player->last_name,
            'dob' => $player->dob->toDateString(),
            'bats' => BattingHand::Left->value,
        ])
        ->assertRedirect(route('players.index'));

    $fresh = $player->fresh();
    expect($fresh->first_name)->toBe('New')
        ->and($fresh->bats)->toBe(BattingHand::Left);
});

test('admin can archive a player via soft delete', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);
    $player = Player::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('players.destroy', $player))
        ->assertRedirect(route('players.index'));

    expect(Player::query()->withoutGlobalScopes()->withTrashed()->find($player->id)?->trashed())
        ->toBeTrue();
});

test('players from another organization 404 via route binding', function () {
    $admin = playerMemberLogin($this->org, OrganizationRole::Admin);
    $otherPlayer = Player::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('players.update', $otherPlayer), [
            'first_name' => 'Hijack',
            'last_name' => 'Attempt',
            'dob' => '2014-04-12',
        ])
        ->assertNotFound();
});
