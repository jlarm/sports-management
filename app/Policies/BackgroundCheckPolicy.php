<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\BackgroundCheck;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class BackgroundCheckPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function update(User $user, BackgroundCheck $check): bool
    {
        return $this->tenant->isResolved()
            && $check->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, BackgroundCheck $check): bool
    {
        return $this->update($user, $check);
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
