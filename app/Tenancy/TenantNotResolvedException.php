<?php

declare(strict_types=1);

namespace App\Tenancy;

use RuntimeException;

final class TenantNotResolvedException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No current tenant is bound. Tenant-scoped data cannot be accessed '
            .'outside an authenticated web request without explicitly binding '
            .'CurrentTenant (e.g., at the top of a queued job).'
        );
    }
}
