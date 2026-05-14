<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Division;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class TeamsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $seasons = Season::query()->orderByDesc('is_active')->orderByDesc('start_date')->get();
        $divisions = Division::query()->orderBy('display_order')->orderBy('name')->get();

        $activeSeason = $seasons->firstWhere('is_active', true);
        $defaultSeasonId = $activeSeason !== null
            ? $activeSeason->id
            : $seasons->first()?->id;

        $requestedSeasonId = $request->integer('season') > 0
            ? $request->integer('season')
            : $defaultSeasonId;

        $selectedSeasonId = $seasons->contains('id', $requestedSeasonId)
            ? $requestedSeasonId
            : $defaultSeasonId;

        $teams = $selectedSeasonId === null
            ? collect()
            : Team::query()
                ->with('season', 'division')
                ->where('season_id', $selectedSeasonId)
                ->orderBy('division_id')
                ->orderBy('name')
                ->get();

        return Inertia::render('teams/Index', [
            'teams' => TeamResource::collection($teams)->toArray($request),
            'seasons' => $seasons
                ->map(fn (Season $season): array => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'is_active' => $season->is_active,
                ])
                ->values()
                ->all(),
            'divisions' => $divisions
                ->map(fn (Division $division): array => [
                    'id' => $division->id,
                    'name' => $division->name,
                ])
                ->values()
                ->all(),
            'selectedSeasonId' => $selectedSeasonId,
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $name = $request->string('name')->toString();
        $seasonId = $request->integer('season_id');
        $divisionId = $request->integer('division_id');
        $rawSlug = $request->string('slug')->toString();
        $slug = $rawSlug !== '' ? $rawSlug : Str::slug($name);

        Team::create([
            'name' => $name,
            'slug' => $slug,
            'season_id' => $seasonId,
            'division_id' => $divisionId,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.index', ['season' => $seasonId]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $name = $request->string('name')->toString();
        $seasonId = $request->integer('season_id');
        $divisionId = $request->integer('division_id');
        $rawSlug = $request->string('slug')->toString();
        $slug = $rawSlug !== '' ? $rawSlug : Str::slug($name);

        $team->update([
            'name' => $name,
            'slug' => $slug,
            'season_id' => $seasonId,
            'division_id' => $divisionId,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.index', ['season' => $seasonId]);
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $seasonId = $team->season_id;
        $team->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team archived.')]);

        return to_route('teams.index', ['season' => $seasonId]);
    }
}
