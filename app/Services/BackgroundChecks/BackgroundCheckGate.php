<?php

declare(strict_types=1);

namespace App\Services\BackgroundChecks;

use App\Enums\BackgroundCheckStatus;
use App\Enums\TeamRole;
use App\Models\BackgroundCheck;
use Carbon\CarbonImmutable;

final class BackgroundCheckGate
{
    public function hasCurrentClearedCheck(int $organizationId, int $userId): bool
    {
        $check = BackgroundCheck::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', BackgroundCheckStatus::Cleared->value)
            ->first();

        if ($check === null) {
            return false;
        }

        if ($check->cleared_through === null) {
            return true;
        }

        $today = CarbonImmutable::today();

        return ! $check->cleared_through->lt($today);
    }

    public function roleRequiresCheck(TeamRole $role): bool
    {
        return in_array($role, [TeamRole::HeadCoach, TeamRole::AssistantCoach], true);
    }
}
