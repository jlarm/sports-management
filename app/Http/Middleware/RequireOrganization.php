<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireOrganization
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->tenant->isResolved(), 403);

        return $next($request);
    }
}
