<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SwitchOrganizationController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        abort_unless($user->belongsToOrganization($organization), 403);

        $request->session()->put('current_org_id', $organization->id);

        return back();
    }
}
