<?php

declare(strict_types=1);

namespace App\Services\Seasons;

use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final readonly class SeasonRolloverService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{
     *     name: string,
     *     start_date: string,
     *     end_date: string,
     *     clone_teams: bool,
     *     clone_roster_division_ids: array<int, int>,
     * }  $input
     */
    public function execute(Season $source, array $input): Season
    {
        return DB::transaction(function () use ($source, $input): Season {
            // 1. Deactivate every currently-active season in this org so the
            // partial-unique constraint on (organization_id, is_active) is free.
            Season::query()
                ->where('organization_id', $source->organization_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // 2. Create the new season as active.
            $newSeason = new Season;
            $newSeason->forceFill([
                'organization_id' => $source->organization_id,
                'name' => $input['name'],
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'is_active' => true,
                'is_registration_open' => false,
            ])->save();

            $teamMap = [];
            if ($input['clone_teams']) {
                $teamMap = $this->cloneTeams($source, $newSeason);
            }

            $rosterDivisionIds = $input['clone_roster_division_ids'];
            $rostersCloned = 0;
            if ($teamMap !== [] && $rosterDivisionIds !== []) {
                $rostersCloned = $this->cloneRosters($source, $teamMap, $rosterDivisionIds);
            }

            $this->audit->log(
                organizationId: $source->organization_id,
                action: 'season.rolled_over',
                subject: $newSeason,
                payload: [
                    'source_season_id' => $source->id,
                    'cloned_teams' => count($teamMap),
                    'cloned_roster_division_ids' => $rosterDivisionIds,
                    'roster_entries_cloned' => $rostersCloned,
                ],
            );

            return $newSeason;
        });
    }

    /**
     * @return array<int, int> map of old_team_id => new_team_id
     */
    private function cloneTeams(Season $source, Season $newSeason): array
    {
        $teamMap = [];

        $oldTeams = Team::query()
            ->where('organization_id', $source->organization_id)
            ->where('season_id', $source->id)
            ->orderBy('id')
            ->get();

        foreach ($oldTeams as $oldTeam) {
            $newTeam = new Team;
            $newTeam->forceFill([
                'organization_id' => $source->organization_id,
                'season_id' => $newSeason->id,
                'division_id' => $oldTeam->division_id,
                'name' => $oldTeam->name,
                'slug' => $oldTeam->slug,
            ])->save();
            $teamMap[$oldTeam->id] = $newTeam->id;
        }

        return $teamMap;
    }

    /**
     * @param  array<int, int>  $teamMap
     * @param  array<int, int>  $divisionIds
     */
    private function cloneRosters(Season $source, array $teamMap, array $divisionIds): int
    {
        $oldTeams = Team::query()
            ->where('organization_id', $source->organization_id)
            ->where('season_id', $source->id)
            ->whereIn('division_id', $divisionIds)
            ->with('rosterEntries')
            ->get();

        $count = 0;
        foreach ($oldTeams as $oldTeam) {
            $newTeamId = $teamMap[$oldTeam->id];

            foreach ($oldTeam->rosterEntries as $entry) {
                $copy = new TeamPlayer;
                $copy->forceFill([
                    'team_id' => $newTeamId,
                    'player_id' => $entry->player_id,
                    'jersey_number' => $entry->jersey_number,
                    'primary_position' => $entry->primary_position,
                    'is_captain' => $entry->is_captain,
                ])->save();
                $count++;
            }
        }

        return $count;
    }
}
