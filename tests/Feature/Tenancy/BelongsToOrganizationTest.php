<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Tests\Fixtures\Models\TenantWidget;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('creating a model auto-fills organization_id from the current tenant', function () {
    $this->tenant->set($this->orgA);

    $widget = TenantWidget::create(['name' => 'A widget']);

    expect($widget->organization_id)->toBe($this->orgA->id);
});

test('global scope filters cross-organization rows', function () {
    $this->tenant->set($this->orgA);
    TenantWidget::create(['name' => 'A1']);
    TenantWidget::create(['name' => 'A2']);

    $this->tenant->set($this->orgB);
    TenantWidget::create(['name' => 'B1']);

    expect(TenantWidget::pluck('name')->all())->toBe(['B1']);

    $this->tenant->set($this->orgA);
    expect(TenantWidget::pluck('name')->all())->toBe(['A1', 'A2']);
});

test('query throws when no tenant is bound', function () {
    $this->tenant->clear();

    expect(fn () => TenantWidget::count())->toThrow(TenantNotResolvedException::class);
});

test('organization relation is available on tenant models', function () {
    $this->tenant->set($this->orgA);
    $widget = TenantWidget::create(['name' => 'rel']);

    expect($widget->organization->is($this->orgA))->toBeTrue();
});
