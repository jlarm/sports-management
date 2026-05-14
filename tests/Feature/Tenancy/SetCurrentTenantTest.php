<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Http\Middleware\SetCurrentTenant;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', SetCurrentTenant::class])
        ->get('/_test/tenant', function () {
            $tenant = app(CurrentTenant::class);

            return response()->json([
                'resolved' => $tenant->isResolved(),
                'id' => $tenant->isResolved() ? $tenant->id() : null,
            ]);
        });
});

test('middleware binds tenant from session for a member', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $user->organizations()->attach($org, ['role' => OrganizationRole::Coach->value]);

    $this->actingAs($user)
        ->withSession(['current_org_id' => $org->id])
        ->get('/_test/tenant')
        ->assertOk()
        ->assertJson(['resolved' => true, 'id' => $org->id]);
});

test('middleware falls back to first organization when session is empty', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    $this->actingAs($user)
        ->get('/_test/tenant')
        ->assertOk()
        ->assertJson(['resolved' => true, 'id' => $org->id]);
});

test('middleware aborts 403 when user is not a member of the session organization', function () {
    $user = User::factory()->create();
    $otherOrg = Organization::factory()->create();

    $this->actingAs($user)
        ->withSession(['current_org_id' => $otherOrg->id])
        ->get('/_test/tenant')
        ->assertForbidden();
});

test('middleware leaves tenant unresolved when user has no organizations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/_test/tenant')
        ->assertOk()
        ->assertJson(['resolved' => false, 'id' => null]);
});
