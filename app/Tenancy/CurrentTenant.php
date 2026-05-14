<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Organization;

final class CurrentTenant
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function isResolved(): bool
    {
        return $this->organization !== null;
    }

    public function get(): Organization
    {
        if ($this->organization === null) {
            throw TenantNotResolvedException::make();
        }

        return $this->organization;
    }

    public function id(): int
    {
        return $this->get()->id;
    }
}
