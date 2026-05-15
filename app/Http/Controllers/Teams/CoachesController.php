<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\Coaches\StoreTeamCoachRequest;
use App\Http\Requests\Teams\Coaches\UpdateTeamCoachRequest;
use App\Models\Team;
use App\Models\TeamUser;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class CoachesController extends Controller
{
    public function store(StoreTeamCoachRequest $request, Team $team, AuditLogger $audit): RedirectResponse
    {
        $entry = TeamUser::create([
            'team_id' => $team->id,
            'user_id' => $request->integer('user_id'),
            'role' => $request->string('role')->toString(),
        ]);

        $audit->log(
            organizationId: $team->organization_id,
            action: 'team_user.assigned',
            subject: $team,
            payload: ['user_id' => $entry->user_id, 'role' => $entry->role->value],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach assigned.')]);

        return to_route('teams.roster.show', $team);
    }

    public function update(UpdateTeamCoachRequest $request, Team $team, TeamUser $coach, AuditLogger $audit): RedirectResponse
    {
        abort_unless($coach->team_id === $team->id, 404);

        $previous = $coach->role->value;
        $coach->update([
            'role' => $request->string('role')->toString(),
        ]);

        $audit->log(
            organizationId: $team->organization_id,
            action: 'team_user.role_changed',
            subject: $team,
            payload: ['user_id' => $coach->user_id, 'from' => $previous, 'to' => $coach->role->value],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach role updated.')]);

        return to_route('teams.roster.show', $team);
    }

    public function destroy(Team $team, TeamUser $coach, AuditLogger $audit): RedirectResponse
    {
        abort_unless($coach->team_id === $team->id, 404);

        $this->authorize('manageCoaches', $team);

        $userId = $coach->user_id;
        $role = $coach->role->value;
        $coach->delete();

        $audit->log(
            organizationId: $team->organization_id,
            action: 'team_user.removed',
            subject: $team,
            payload: ['user_id' => $userId, 'role' => $role],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach removed.')]);

        return to_route('teams.roster.show', $team);
    }
}
