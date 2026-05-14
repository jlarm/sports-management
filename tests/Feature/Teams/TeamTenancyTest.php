<?php

declare(strict_types=1);

use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('team slug must be unique within an organization and season', function () {
    $season = Season::factory()->for($this->orgA)->create();
    $division = Division::factory()->for($this->orgA)->create();

    Team::factory()->for($this->orgA)->create([
        'season_id' => $season->id,
        'division_id' => $division->id,
        'slug' => '10u-red',
    ]);

    expect(fn () => Team::factory()->for($this->orgA)->create([
        'season_id' => $season->id,
        'division_id' => $division->id,
        'slug' => '10u-red',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('same slug is allowed across different seasons in the same organization', function () {
    $springSeason = Season::factory()->for($this->orgA)->create(['name' => 'Spring 26']);
    $fallSeason = Season::factory()->for($this->orgA)->create(['name' => 'Fall 26']);
    $division = Division::factory()->for($this->orgA)->create();

    Team::factory()->for($this->orgA)->create([
        'season_id' => $springSeason->id,
        'division_id' => $division->id,
        'slug' => '10u-red',
    ]);
    Team::factory()->for($this->orgA)->create([
        'season_id' => $fallSeason->id,
        'division_id' => $division->id,
        'slug' => '10u-red',
    ]);

    expect(Team::query()->withoutGlobalScopes()->where('slug', '10u-red')->count())->toBe(2);
});

test('same slug and season is allowed across different organizations', function () {
    $seasonA = Season::factory()->for($this->orgA)->create();
    $divisionA = Division::factory()->for($this->orgA)->create();
    $seasonB = Season::factory()->for($this->orgB)->create();
    $divisionB = Division::factory()->for($this->orgB)->create();

    Team::factory()->for($this->orgA)->create([
        'season_id' => $seasonA->id,
        'division_id' => $divisionA->id,
        'slug' => 'shared',
    ]);
    Team::factory()->for($this->orgB)->create([
        'season_id' => $seasonB->id,
        'division_id' => $divisionB->id,
        'slug' => 'shared',
    ]);

    expect(Team::query()->withoutGlobalScopes()->where('slug', 'shared')->count())->toBe(2);
});

test('global scope filters teams across organizations', function () {
    $seasonA = Season::factory()->for($this->orgA)->create();
    $divisionA = Division::factory()->for($this->orgA)->create();
    $seasonB = Season::factory()->for($this->orgB)->create();
    $divisionB = Division::factory()->for($this->orgB)->create();

    Team::factory()->for($this->orgA)->create([
        'season_id' => $seasonA->id,
        'division_id' => $divisionA->id,
        'name' => 'Aces',
    ]);
    Team::factory()->for($this->orgB)->create([
        'season_id' => $seasonB->id,
        'division_id' => $divisionB->id,
        'name' => 'Bears',
    ]);

    $this->tenant->set($this->orgA);
    expect(Team::query()->pluck('name')->all())->toBe(['Aces']);

    $this->tenant->set($this->orgB);
    expect(Team::query()->pluck('name')->all())->toBe(['Bears']);
});

test('querying teams without a tenant fails closed', function () {
    $season = Season::factory()->for($this->orgA)->create();
    $division = Division::factory()->for($this->orgA)->create();
    Team::factory()->for($this->orgA)->create([
        'season_id' => $season->id,
        'division_id' => $division->id,
    ]);
    $this->tenant->clear();

    expect(fn () => Team::query()->count())->toThrow(TenantNotResolvedException::class);
});

test('creating a team auto-fills organization_id from the current tenant', function () {
    $season = Season::factory()->for($this->orgA)->create();
    $division = Division::factory()->for($this->orgA)->create();
    $this->tenant->set($this->orgA);

    $team = Team::create([
        'season_id' => $season->id,
        'division_id' => $division->id,
        'name' => 'Auto Team',
        'slug' => 'auto-team',
    ]);

    expect($team->organization_id)->toBe($this->orgA->id);
});

test('team belongs to season and division', function () {
    $season = Season::factory()->for($this->orgA)->create(['name' => 'Spring 26']);
    $division = Division::factory()->for($this->orgA)->create(['name' => '10U']);
    $this->tenant->set($this->orgA);

    $team = Team::factory()->for($this->orgA)->create([
        'season_id' => $season->id,
        'division_id' => $division->id,
    ])->fresh();

    expect($team->season?->name)->toBe('Spring 26')
        ->and($team->division?->name)->toBe('10U');
});
