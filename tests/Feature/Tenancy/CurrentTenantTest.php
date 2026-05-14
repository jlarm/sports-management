<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;

test('current tenant returns bound organization', function () {
    $org = Organization::factory()->create();
    $tenant = app(CurrentTenant::class);

    $tenant->set($org);

    expect($tenant->isResolved())->toBeTrue()
        ->and($tenant->get()->is($org))->toBeTrue()
        ->and($tenant->id())->toBe($org->id);
});

test('current tenant fails closed when unbound', function () {
    $tenant = app(CurrentTenant::class);
    $tenant->clear();

    expect(fn () => $tenant->get())->toThrow(TenantNotResolvedException::class)
        ->and(fn () => $tenant->id())->toThrow(TenantNotResolvedException::class);
});
