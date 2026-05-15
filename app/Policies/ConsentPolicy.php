<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Consent;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class ConsentPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function view(User $user, Consent $consent): bool
    {
        return $this->tenant->isResolved()
            && $consent->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function withdraw(User $user, Consent $consent): bool
    {
        return $this->view($user, $consent) && ! $consent->isWithdrawn();
    }

    private function canManageCurrentOrg(User $user): bool
    {
        if (! $this->tenant->isResolved()) {
            return false;
        }

        $role = $user->roleIn($this->tenant->get());

        return $role instanceof OrganizationRole && $role->canManageOrganization();
    }
}
