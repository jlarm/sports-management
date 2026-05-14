<?php

declare(strict_types=1);

use App\Enums\BattingHand;
use App\Enums\ThrowingHand;
use App\Models\Organization;
use App\Models\Player;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('global scope filters players across organizations', function () {
    Player::factory()->for($this->orgA)->create(['first_name' => 'A1']);
    Player::factory()->for($this->orgA)->create(['first_name' => 'A2']);
    Player::factory()->for($this->orgB)->create(['first_name' => 'B1']);

    $this->tenant->set($this->orgA);
    expect(Player::query()->pluck('first_name')->all())->toContain('A1')->toContain('A2');
    expect(Player::query()->pluck('first_name')->all())->not->toContain('B1');

    $this->tenant->set($this->orgB);
    expect(Player::query()->pluck('first_name')->all())->toBe(['B1']);
});

test('querying players without a tenant fails closed', function () {
    Player::factory()->for($this->orgA)->create();
    $this->tenant->clear();

    expect(fn () => Player::query()->count())->toThrow(TenantNotResolvedException::class);
});

test('creating a player auto-fills organization_id from the current tenant', function () {
    $this->tenant->set($this->orgA);

    $player = Player::create([
        'first_name' => 'Auto',
        'last_name' => 'Filled',
        'dob' => '2014-04-01',
    ]);

    expect($player->organization_id)->toBe($this->orgA->id);
});

test('external_id is unique within an organization but allows multiple NULLs', function () {
    Player::factory()->for($this->orgA)->create(['external_id' => 'EXT-1']);

    expect(fn () => Player::factory()->for($this->orgA)->create(['external_id' => 'EXT-1']))
        ->toThrow(UniqueConstraintViolationException::class);

    // Multiple NULL external_id rows can coexist
    Player::factory()->for($this->orgA)->create(['external_id' => null]);
    Player::factory()->for($this->orgA)->create(['external_id' => null]);
    Player::factory()->for($this->orgA)->create(['external_id' => null]);

    expect(
        Player::query()->withoutGlobalScopes()
            ->where('organization_id', $this->orgA->id)
            ->whereNull('external_id')->count()
    )->toBe(3);
});

test('same external_id can exist in different organizations', function () {
    Player::factory()->for($this->orgA)->create(['external_id' => 'EXT-7']);
    Player::factory()->for($this->orgB)->create(['external_id' => 'EXT-7']);

    expect(Player::query()->withoutGlobalScopes()->where('external_id', 'EXT-7')->count())->toBe(2);
});

test('handedness casts return the enum instances', function () {
    $this->tenant->set($this->orgA);
    $player = Player::factory()->for($this->orgA)->create([
        'bats' => BattingHand::Left->value,
        'throws' => ThrowingHand::Right->value,
    ])->fresh();

    expect($player->bats)->toBe(BattingHand::Left)
        ->and($player->throws)->toBe(ThrowingHand::Right);
});
