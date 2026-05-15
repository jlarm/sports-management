<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class InvitationPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return $this->tenant->isResolved()
            && $invitation->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->tenant->isResolved()
            && $invitation->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        return $this->delete($user, $invitation);
    }

    private function canManageCurrentOrg(User $user): bool
    {
        if (! $this->tenant->isResolved()) {
            return false;
        }

        $role = $user->roleIn($this->tenant->get());

        return $role instanceof \App\Enums\OrganizationRole && $role->canManageOrganization();
    }
}
