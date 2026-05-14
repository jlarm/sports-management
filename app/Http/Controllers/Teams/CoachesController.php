<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\Coaches\StoreTeamCoachRequest;
use App\Http\Requests\Teams\Coaches\UpdateTeamCoachRequest;
use App\Models\Team;
use App\Models\TeamUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class CoachesController extends Controller
{
    public function store(StoreTeamCoachRequest $request, Team $team): RedirectResponse
    {
        TeamUser::create([
            'team_id' => $team->id,
            'user_id' => $request->integer('user_id'),
            'role' => $request->string('role')->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach assigned.')]);

        return to_route('teams.roster.show', $team);
    }

    public function update(UpdateTeamCoachRequest $request, Team $team, TeamUser $coach): RedirectResponse
    {
        abort_unless($coach->team_id === $team->id, 404);

        $coach->update([
            'role' => $request->string('role')->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach role updated.')]);

        return to_route('teams.roster.show', $team);
    }

    public function destroy(Team $team, TeamUser $coach): RedirectResponse
    {
        abort_unless($coach->team_id === $team->id, 404);

        $this->authorize('manageCoaches', $team);

        $coach->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach removed.')]);

        return to_route('teams.roster.show', $team);
    }
}
