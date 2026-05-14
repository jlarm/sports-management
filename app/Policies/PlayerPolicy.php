<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Player;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class PlayerPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant->isResolved()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function view(User $user, Player $player): bool
    {
        return $this->tenant->isResolved()
            && $player->organization_id === $this->tenant->id()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function update(User $user, Player $player): bool
    {
        return $this->tenant->isResolved()
            && $player->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, Player $player): bool
    {
        return $this->update($user, $player);
    }

    public function restore(User $user, Player $player): bool
    {
        return $this->update($user, $player);
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
