<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Season;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('partial unique allows two active seasons in different organizations', function () {
    Season::factory()->for($this->orgA)->active()->create(['name' => 'Spring 26']);
    Season::factory()->for($this->orgB)->active()->create(['name' => 'Spring 26']);

    expect(Season::query()->withoutGlobalScopes()->where('is_active', true)->count())->toBe(2);
});

test('partial unique rejects two active seasons in the same organization', function () {
    Season::factory()->for($this->orgA)->active()->create(['name' => 'Spring 26']);

    expect(fn () => Season::factory()->for($this->orgA)->active()->create(['name' => 'Fall 26']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('inactive seasons can coexist freely', function () {
    Season::factory()->for($this->orgA)->count(3)->create();

    expect(Season::query()->withoutGlobalScopes()->where('organization_id', $this->orgA->id)->count())->toBe(3);
});

test('global scope filters seasons across organizations', function () {
    Season::factory()->for($this->orgA)->create(['name' => 'A1']);
    Season::factory()->for($this->orgA)->create(['name' => 'A2']);
    Season::factory()->for($this->orgB)->create(['name' => 'B1']);

    $this->tenant->set($this->orgA);
    expect(Season::query()->pluck('name')->all())->toBe(['A1', 'A2']);

    $this->tenant->set($this->orgB);
    expect(Season::query()->pluck('name')->all())->toBe(['B1']);
});

test('querying seasons without a tenant fails closed', function () {
    Season::factory()->for($this->orgA)->create();
    $this->tenant->clear();

    expect(fn () => Season::query()->count())->toThrow(TenantNotResolvedException::class);
});

test('creating a season auto-fills organization_id from the current tenant', function () {
    $this->tenant->set($this->orgA);

    $season = Season::create([
        'name' => 'Auto 26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-01',
    ]);

    expect($season->organization_id)->toBe($this->orgA->id);
});

test('unique (organization_id, name) prevents duplicate names within an org', function () {
    Season::factory()->for($this->orgA)->create(['name' => 'Spring 26']);

    expect(fn () => Season::factory()->for($this->orgA)->create(['name' => 'Spring 26']))
        ->toThrow(UniqueConstraintViolationException::class);
});
