---
name: season-archiving
description: Logical season archiving via session-scoped global scope, and the rollover wizard pattern
metadata:
  type: project
---

**Archiving = scoping, not moving data.** Never delete or migrate old season data. Just hide it from the default view by anchoring everything to `season_id`.

**Session-driven active season:**
1. On login, look up the org's `is_active` season and store `current_season_id` in the session.
2. Navbar has a "Current Season: Spring 2025 ▼" dropdown. Selecting another season updates the session and reloads.
3. Models that are seasonal apply a global scope:

```php
static::addGlobalScope('current_season', function (Builder $q) {
    if (session()->has('current_season_id')) {
        $q->where('season_id', session('current_season_id'));
    }
});
```

`Team::all()` then auto-filters to the active season. Old seasons are "archived" because they simply don't appear.

**Rollover wizard (new season):**
- Create new `Season` record (mark `is_active = true`, deactivate old).
- Clone team structure: loop old teams, insert new rows with `season_id = new`, copy `name` and `division_id`.
- DO NOT copy rosters (`team_player`). Rosters start empty because kids age up or quit.
- Update session `current_season_id` and redirect.

Wrap the rollover in `DB::transaction()`. See [[global-vs-seasonal]] for which tables need `season_id` and [[development-roadmap]] for where this fits in the build order.
