<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property OrganizationRole $role
 */
final class OrganizationUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'organization_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
        ];
    }
}
