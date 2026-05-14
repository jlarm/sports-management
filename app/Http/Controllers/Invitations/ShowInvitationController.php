<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowInvitationController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $invitation = Invitation::query()
            ->withoutGlobalScopes()
            ->with('organization', 'invitedBy')
            ->where('token_hash', Invitation::hashToken($token))
            ->firstOrFail();

        $user = $request->user();
        assert($user instanceof User);

        $organization = $invitation->organization;
        assert($organization instanceof Organization);

        return Inertia::render('invitations/Show', [
            'invitation' => [
                'organization_name' => $organization->name,
                'role' => $invitation->role->value,
                'email' => $invitation->email,
                'status' => $invitation->status()->value,
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'email_matches' => mb_strtolower($invitation->email) === mb_strtolower($user->email),
            ],
            'token' => $token,
        ]);
    }
}
