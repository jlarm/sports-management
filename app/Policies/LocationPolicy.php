<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final class LocationPolicy
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant->isResolved()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function view(User $user, Location $location): bool
    {
        return $this->tenant->isResolved()
            && $location->organization_id === $this->tenant->id()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function update(User $user, Location $location): bool
    {
        return $location->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->update($user, $location);
    }

    public function restore(User $user, Location $location): bool
    {
        return $this->update($user, $location);
    }

    private function canManageCurrentOrg(User $user): bool
    {
        if (! $this->tenant->isResolved()) {
            return false;
        }

        $role = $user->roleIn($this->tenant->get());

        return $role !== null && $role->canManageOrganization();
    }
}
