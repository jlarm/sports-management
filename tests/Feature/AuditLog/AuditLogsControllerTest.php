<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

function auditAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('admin can view the audit log paginated and tenant-scoped', function () {
    $admin = auditAdminLogin($this->org);
    $other = Organization::factory()->create();
    AuditLog::factory()->for($this->org)->count(3)->create(['action' => 'player.created']);
    AuditLog::factory()->for($other)->count(2)->create(['action' => 'player.created']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('audit-logs/Index')
            ->has('entries', 3)
            ->where('pagination.total', 3)
        );
});

test('coach cannot view the audit log', function () {
    $coach = User::factory()->create(['email_verified_at' => now()]);
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($coach->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('audit-logs.index'))
        ->assertForbidden();
});

test('action prefix filter narrows the list', function () {
    $admin = auditAdminLogin($this->org);
    AuditLog::factory()->for($this->org)->create(['action' => 'player.created']);
    AuditLog::factory()->for($this->org)->create(['action' => 'player.archived']);
    AuditLog::factory()->for($this->org)->create(['action' => 'form.published']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('audit-logs.index', ['action' => 'player.']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pagination.total', 2));
});

test('from/to date filters narrow the list', function () {
    $admin = auditAdminLogin($this->org);
    AuditLog::factory()->for($this->org)->create(['action' => 'old', 'created_at' => now()->subMonth()]);
    AuditLog::factory()->for($this->org)->create(['action' => 'recent', 'created_at' => now()]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('audit-logs.index', ['from' => now()->subWeek()->toDateString()]))
        ->assertInertia(fn ($page) => $page->where('pagination.total', 1));

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('audit-logs.index', ['to' => now()->subWeek()->toDateString()]))
        ->assertInertia(fn ($page) => $page->where('pagination.total', 1));
});

test('AuditLogPolicy fails closed when the tenant is unresolved', function () {
    $admin = auditAdminLogin($this->org);

    $policy = app(App\Policies\AuditLogPolicy::class);
    app(App\Tenancy\CurrentTenant::class)->clear();

    expect($policy->viewAny($admin))->toBeFalse();
});

test('the entries payload includes the actor name when present', function () {
    $admin = auditAdminLogin($this->org);
    $actor = User::factory()->create(['name' => 'Acting Admin']);
    AuditLog::factory()->for($this->org)->create(['actor_user_id' => $actor->id, 'action' => 'player.created']);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('audit-logs.index'))
        ->assertInertia(fn ($page) => $page->where('entries.0.actor.name', 'Acting Admin'));
});
