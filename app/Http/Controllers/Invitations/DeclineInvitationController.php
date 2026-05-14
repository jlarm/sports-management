<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class DeclineInvitationController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::query()
            ->withoutGlobalScopes()
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

        $invitation->forceFill(['declined_at' => now()])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invitation declined.'),
        ]);

        return to_route('home');
    }
}
