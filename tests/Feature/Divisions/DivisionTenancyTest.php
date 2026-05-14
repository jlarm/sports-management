<?php

declare(strict_types=1);

use App\Models\Division;
use App\Models\Organization;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('division names must be unique within an organization', function () {
    Division::factory()->for($this->orgA)->create(['name' => '10U']);

    expect(fn () => Division::factory()->for($this->orgA)->create(['name' => '10U']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the same division name can live in different organizations', function () {
    Division::factory()->for($this->orgA)->create(['name' => '10U']);
    Division::factory()->for($this->orgB)->create(['name' => '10U']);

    expect(Division::query()->withoutGlobalScopes()->where('name', '10U')->count())->toBe(2);
});

test('global scope filters divisions across organizations', function () {
    Division::factory()->for($this->orgA)->create(['name' => 'A1', 'display_order' => 1]);
    Division::factory()->for($this->orgA)->create(['name' => 'A2', 'display_order' => 2]);
    Division::factory()->for($this->orgB)->create(['name' => 'B1', 'display_order' => 1]);

    $this->tenant->set($this->orgA);
    expect(Division::query()->pluck('name')->all())->toContain('A1')->toContain('A2');
    expect(Division::query()->pluck('name')->all())->not->toContain('B1');

    $this->tenant->set($this->orgB);
    expect(Division::query()->pluck('name')->all())->toBe(['B1']);
});

test('querying divisions without a tenant fails closed', function () {
    Division::factory()->for($this->orgA)->create();
    $this->tenant->clear();

    expect(fn () => Division::query()->count())->toThrow(TenantNotResolvedException::class);
});

test('creating a division auto-fills organization_id from the current tenant', function () {
    $this->tenant->set($this->orgA);

    $division = Division::create(['name' => '14U'])->fresh();

    expect($division->organization_id)->toBe($this->orgA->id)
        ->and($division->display_order)->toBe(0);
});
