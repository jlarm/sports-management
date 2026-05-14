<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

final class AcceptInvitationController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::query()
            ->withoutGlobalScopes()
            ->with('organization')
            ->where('token_hash', Invitation::hashToken($token))
            ->firstOrFail();

        if ($invitation->status() !== InvitationStatus::Pending) {
            abort(410);
        }

        $user = $request->user();
        assert($user instanceof User);

        if (mb_strtolower($invitation->email) !== mb_strtolower($user->email)) {
            abort(403);
        }

        $organization = $invitation->organization;
        assert($organization instanceof Organization);

        DB::transaction(function () use ($invitation, $organization, $user): void {
            $organization->members()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role->value],
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        $request->session()->put('current_org_id', $invitation->organization_id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Welcome to :name.', ['name' => $organization->name]),
        ]);

        return to_route('dashboard');
    }
}
