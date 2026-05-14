<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Coach = 'coach';
    case Guardian = 'guardian';

    public function canManageOrganization(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Coach, self::Guardian => false,
        };
    }
}
