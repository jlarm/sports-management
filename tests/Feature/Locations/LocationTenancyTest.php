<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Organization;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('location names must be unique within an organization', function () {
    Location::factory()->for($this->orgA)->create(['name' => 'Main Field']);

    expect(fn () => Location::factory()->for($this->orgA)->create(['name' => 'Main Field']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the same location name can live in different organizations', function () {
    Location::factory()->for($this->orgA)->create(['name' => 'Main Field']);
    Location::factory()->for($this->orgB)->create(['name' => 'Main Field']);

    expect(Location::query()->withoutGlobalScopes()->where('name', 'Main Field')->count())->toBe(2);
});

test('global scope filters locations across organizations', function () {
    Location::factory()->for($this->orgA)->create(['name' => 'A1']);
    Location::factory()->for($this->orgA)->create(['name' => 'A2']);
    Location::factory()->for($this->orgB)->create(['name' => 'B1']);

    $this->tenant->set($this->orgA);
    expect(Location::query()->pluck('name')->all())->toContain('A1')->toContain('A2');
    expect(Location::query()->pluck('name')->all())->not->toContain('B1');

    $this->tenant->set($this->orgB);
    expect(Location::query()->pluck('name')->all())->toBe(['B1']);
});

test('querying locations without a tenant fails closed', function () {
    Location::factory()->for($this->orgA)->create();
    $this->tenant->clear();

    expect(fn () => Location::query()->count())->toThrow(TenantNotResolvedException::class);
});

test('creating a location auto-fills organization_id from the current tenant', function () {
    $this->tenant->set($this->orgA);

    $location = Location::create(['name' => 'Aux Field', 'address' => '1 First St']);

    expect($location->organization_id)->toBe($this->orgA->id)
        ->and($location->address)->toBe('1 First St');
});
