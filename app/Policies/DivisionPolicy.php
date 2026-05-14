<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Division;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final class DivisionPolicy
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant->isResolved()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function view(User $user, Division $division): bool
    {
        return $this->tenant->isResolved()
            && $division->organization_id === $this->tenant->id()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function update(User $user, Division $division): bool
    {
        return $division->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, Division $division): bool
    {
        return $this->update($user, $division);
    }

    public function restore(User $user, Division $division): bool
    {
        return $this->update($user, $division);
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
