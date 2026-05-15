<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Guardian;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class GuardianPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $this->tenant->isResolved()
            && $guardian->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->view($user, $guardian);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $this->view($user, $guardian);
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
