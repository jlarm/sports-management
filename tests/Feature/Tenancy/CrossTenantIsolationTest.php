<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Tests\Support\TenancyEndpoints;

test('cross-tenant {route} returns the expected status', function (string $name, Closure $build): void {
    // Seed the foreign org and its target record with the tenant unbound so
    // factories don't pick up the current Org A as the owner.
    app(CurrentTenant::class)->clear();
    $orgB = Organization::factory()->create();
    $spec = $build($orgB);

    // Now seed Org A and act as one of its admins.
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->organizations()->attach($orgA, ['role' => OrganizationRole::Admin->value]);

    $response = $this->actingAs($admin->fresh())
        ->withSession(['current_org_id' => $orgA->id])
        ->call($spec['method'], $spec['url'], $spec['payload']);

    expect($response->getStatusCode())
        ->toBe($spec['expected_status'], "Route {$name} should reject cross-tenant access");
})->with(TenancyEndpoints::cases());
