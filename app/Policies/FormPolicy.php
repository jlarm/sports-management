<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Form;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class FormPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant->isResolved()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function view(User $user, Form $form): bool
    {
        return $this->tenant->isResolved()
            && $form->organization_id === $this->tenant->id()
            && $user->belongsToOrganization($this->tenant->get());
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function update(User $user, Form $form): bool
    {
        return $this->tenant->isResolved()
            && $form->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, Form $form): bool
    {
        return $this->update($user, $form);
    }

    public function restore(User $user, Form $form): bool
    {
        return $this->update($user, $form);
    }

    public function publish(User $user, Form $form): bool
    {
        return $this->update($user, $form);
    }

    public function close(User $user, Form $form): bool
    {
        return $this->update($user, $form);
    }

    public function viewSubmissions(User $user, Form $form): bool
    {
        return $this->tenant->isResolved()
            && $form->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
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
