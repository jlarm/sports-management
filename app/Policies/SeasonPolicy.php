<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Season;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class SeasonPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant->isResolved()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function view(User $user, Season $season): bool
    {
        return $this->tenant->isResolved()
            && $season->organization_id === $this->tenant->id()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function update(User $user, Season $season): bool
    {
        return $season->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, Season $season): bool
    {
        return $this->update($user, $season);
    }

    public function restore(User $user, Season $season): bool
    {
        return $this->update($user, $season);
    }

    public function activate(User $user, Season $season): bool
    {
        return $this->update($user, $season);
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
