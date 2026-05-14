<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\Roster\StoreRosterEntryRequest;
use App\Http\Requests\Teams\Roster\UpdateRosterEntryRequest;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamUser;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RosterController extends Controller
{
    public function show(Team $team): Response
    {
        $this->authorize('viewRoster', $team);

        $team->loadMissing('season', 'division');

        $entries = TeamPlayer::query()
            ->where('team_id', $team->id)
            ->with('player:id,first_name,last_name,dob,bats,throws')
            ->orderBy('jersey_number')
            ->orderBy('id')
            ->get()
            ->map(fn (TeamPlayer $entry): array => [
                'id' => $entry->id,
                'jersey_number' => $entry->jersey_number,
                'primary_position' => $entry->primary_position,
                'is_captain' => $entry->is_captain,
                'player' => $entry->player !== null
                    ? [
                        'id' => $entry->player->id,
                        'first_name' => $entry->player->first_name,
                        'last_name' => $entry->player->last_name,
                        'dob' => $entry->player->dob->toDateString(),
                        'bats' => $entry->player->bats?->value,
                        'throws' => $entry->player->throws?->value,
                    ]
                    : null,
            ])
            ->all();

        $availablePlayers = Player::query()
            ->whereNotIn('id', $team->players()->pluck('players.id'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Player $player): array => [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'dob' => $player->dob->toDateString(),
            ])
            ->all();

        $coaches = TeamUser::query()
            ->where('team_id', $team->id)
            ->with('user:id,name,email')
            ->orderBy('role')
            ->orderBy('id')
            ->get()
            ->map(fn (TeamUser $coach): array => [
                'id' => $coach->id,
                'user_id' => $coach->user_id,
                'role' => $coach->role->value,
                'role_label' => $coach->role->label(),
                'user' => $coach->user !== null
                    ? [
                        'id' => $coach->user->id,
                        'name' => $coach->user->name,
                        'email' => $coach->user->email,
                    ]
                    : null,
            ])
            ->all();

        $orgId = app(CurrentTenant::class)->id();
        $availableMembers = User::query()
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey($orgId))
            ->orderBy('name')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ])
            ->all();

        $roleOptions = array_map(
            fn (TeamRole $role): array => ['value' => $role->value, 'label' => $role->label()],
            TeamRole::cases(),
        );

        return Inertia::render('teams/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'season_id' => $team->season_id,
                'division_id' => $team->division_id,
                'season_name' => $team->season?->name,
                'division_name' => $team->division?->name,
            ],
            'rosterEntries' => $entries,
            'availablePlayers' => $availablePlayers,
            'coaches' => $coaches,
            'availableMembers' => $availableMembers,
            'teamRoleOptions' => $roleOptions,
        ]);
    }

    public function store(StoreRosterEntryRequest $request, Team $team): RedirectResponse
    {
        $entry = new TeamPlayer([
            'jersey_number' => $request->filled('jersey_number')
                ? $request->integer('jersey_number')
                : null,
            'primary_position' => $request->string('primary_position')->toString() ?: null,
            'is_captain' => $request->boolean('is_captain'),
        ]);
        $entry->team_id = $team->id;
        $entry->player_id = $request->integer('player_id');
        $entry->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player added to roster.')]);

        return to_route('teams.roster.show', $team);
    }

    public function update(
        UpdateRosterEntryRequest $request,
        Team $team,
        TeamPlayer $rosterEntry,
    ): RedirectResponse {
        abort_unless($rosterEntry->team_id === $team->id, 404);

        $rosterEntry->update([
            'jersey_number' => $request->filled('jersey_number')
                ? $request->integer('jersey_number')
                : null,
            'primary_position' => $request->string('primary_position')->toString() ?: null,
            'is_captain' => $request->boolean('is_captain'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roster entry updated.')]);

        return to_route('teams.roster.show', $team);
    }

    public function destroy(Team $team, TeamPlayer $rosterEntry): RedirectResponse
    {
        abort_unless($rosterEntry->team_id === $team->id, 404);

        $this->authorize('manageRoster', $team);

        $rosterEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player removed from roster.')]);

        return to_route('teams.roster.show', $team);
    }
}
