<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;

it('grants management ability to owners and admins', function (OrganizationRole $role, bool $expected) {
    expect($role->canManageOrganization())->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'coach' => [OrganizationRole::Coach, false],
    'guardian' => [OrganizationRole::Guardian, false],
]);
