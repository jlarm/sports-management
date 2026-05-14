<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

final class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasManagementRole($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) === OrganizationRole::Owner;
    }

    public function restore(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) === OrganizationRole::Owner;
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) === OrganizationRole::Owner;
    }

    private function hasManagementRole(User $user, Organization $organization): bool
    {
        $role = $user->roleIn($organization);

        return $role !== null && $role->canManageOrganization();
    }
}
