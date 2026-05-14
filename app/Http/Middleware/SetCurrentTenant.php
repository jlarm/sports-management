<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetCurrentTenant
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    /**
     * Resolve the current organization from the session and bind it for the
     * request. Falls back to the user's first organization when the session has
     * no current org. Aborts with 403 if the user is not a member of the
     * requested organization.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $organization = $this->resolveOrganization($request, $user);

        if ($organization === null) {
            return $next($request);
        }

        abort_unless($user->belongsToOrganization($organization), 403);

        $this->tenant->set($organization);
        $request->session()->put('current_org_id', $organization->id);

        return $next($request);
    }

    private function resolveOrganization(Request $request, User $user): ?Organization
    {
        $sessionOrgId = $request->session()->get('current_org_id');

        if (is_int($sessionOrgId)) {
            return Organization::query()->whereKey($sessionOrgId)->first();
        }

        return $user->organizations()->first();
    }
}
