<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Submission;
use App\Models\User;
use App\Tenancy\CurrentTenant;

final readonly class SubmissionPolicy
{
    public function __construct(private CurrentTenant $tenant) {}

    public function viewAny(User $user): bool
    {
        return $this->canManageCurrentOrg($user);
    }

    public function view(User $user, Submission $submission): bool
    {
        return $this->tenant->isResolved()
            && $submission->organization_id === $this->tenant->id()
            && $this->canManageCurrentOrg($user);
    }

    public function delete(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
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
