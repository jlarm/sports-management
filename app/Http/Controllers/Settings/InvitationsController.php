<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Notifications\OrganizationInvitationNotification;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationsController extends Controller
{
    public function index(Request $request, CurrentTenant $tenant): Response
    {
        $this->authorize('viewAny', Invitation::class);

        $invitations = Invitation::query()
            ->with('invitedBy')
            ->orderByRaw('CASE WHEN accepted_at IS NULL AND declined_at IS NULL AND revoked_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('invitations/Index', [
            'invitations' => InvitationResource::collection($invitations)->toArray($request),
            'organizationName' => $tenant->get()->name,
        ]);
    }

    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        $token = Invitation::mintToken();
        $email = $request->string('email')->lower()->toString();
        $role = $request->string('role')->toString();
        $invitedBy = $request->user()?->id;

        $invitation = DB::transaction(function () use ($email, $role, $token, $invitedBy): Invitation {
            $invitation = Invitation::create([
                'email' => $email,
                'role' => $role,
                'token_hash' => $token['hash'],
                'invited_by_user_id' => $invitedBy,
                'expires_at' => now()->addDays(7),
            ]);

            $invitation->load('organization', 'invitedBy');

            return $invitation;
        });

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation, $token['raw']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('invitations.index');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        if ($invitation->isPending()) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return to_route('invitations.index');
    }
}
