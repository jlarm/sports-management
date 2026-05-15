<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seasons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seasons\RolloverSeasonRequest;
use App\Models\Division;
use App\Models\Season;
use App\Models\Team;
use App\Services\Seasons\SeasonRolloverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class SeasonRolloverController extends Controller
{
    public function show(Season $season): Response
    {
        $this->authorize('rollover', $season);

        $teams = Team::query()
            ->where('season_id', $season->id)
            ->with('division:id,name')
            ->orderBy('name')
            ->get();

        $teamsByDivision = $teams
            ->groupBy('division_id')
            ->map(function (Collection $group): array {
                /** @var Team|null $first */
                $first = $group->first();

                return [
                    'division_id' => $first?->division_id,
                    'division_name' => $first?->division->name ?? 'Unassigned',
                    'teams' => $group->map(fn (Team $team): array => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'slug' => $team->slug,
                    ])->all(),
                ];
            })
            ->values()
            ->all();

        $divisions = Division::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('seasons/Rollover', [
            'source_season' => [
                'id' => $season->id,
                'name' => $season->name,
                'start_date' => $season->start_date->toDateString(),
                'end_date' => $season->end_date->toDateString(),
                'is_active' => $season->is_active,
            ],
            'teams_by_division' => $teamsByDivision,
            'divisions' => $divisions->map(fn (Division $d): array => [
                'id' => $d->id,
                'name' => $d->name,
            ])->all(),
        ]);
    }

    public function store(RolloverSeasonRequest $request, Season $season, SeasonRolloverService $service): RedirectResponse
    {
        /** @var array<int, int> $rosterDivisionIds */
        $rosterDivisionIds = array_values(array_map(
            static fn (mixed $value): int => (int) (is_numeric($value) ? $value : 0),
            (array) $request->input('clone_roster_division_ids', []),
        ));

        $service->execute($season, [
            'name' => $request->string('name')->toString(),
            'start_date' => $request->string('start_date')->toString(),
            'end_date' => $request->string('end_date')->toString(),
            'clone_teams' => $request->boolean('clone_teams'),
            'clone_roster_division_ids' => $rosterDivisionIds,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Season rolled over.')]);

        return to_route('seasons.index');
    }
}
