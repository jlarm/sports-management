---
name: global-vs-seasonal
description: Which entities persist across seasons vs. die with the season — critical for schema decisions
metadata:
  type: project
---

The single biggest schema decision in this app: which data is **global** (persists forever within an org) vs. **seasonal** (scoped to one season).

**Global entities — NO `season_id`:**
- `players` — a kid is the same kid forever. One record per human; never duplicate Johnny when he ages up.
- `divisions` — "10U" is always "10U".
- `locations` / fields — addresses don't change per season.
- `guardians` — parent contact info persists.

**Seasonal entities — MUST have `season_id`:**
- `teams` — "2024 10U Red" is a different DB row than "2025 10U Red".
- `team_player` (roster pivot) — the link between a global player and a seasonal team. No `season_id` needed on the pivot itself because `team_id` already carries the season.
- `games` / events / matches.

**Seasonal data on pivot, not parent:**
- `jersey_number` lives on `team_player`, NOT on `players` (kid is #23 in Spring, #5 in Fall).
- `primary_position` can be global (player preference on `players`) OR seasonal (role on `team_player`) — decide per use case.

**Master list view:** "All Players" admin view ignores the season scope so admins see every kid who's ever played. See [[season-archiving]] for the global scope mechanism and [[schema-plan]] for column-level detail.
