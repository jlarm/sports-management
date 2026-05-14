---
name: schema-plan
description: Full database schema plan — tables, columns, keys for the travel baseball SaaS
metadata:
  type: project
---

Migration build order: Users/Orgs → Settings (seasons, divisions, locations) → Players/Guardians → Teams/Rosters → Forms/Submissions. Index every `organization_id` and `season_id`. Consider UUID primary keys to avoid guessable URLs.

### SaaS & Auth
- **`users`** — standard Laravel: `id, name, email (unique), password, remember_token, timestamps`. No `organization_id` here.
- **`organizations`** — `id, name, slug (unique), owner_id → users, logo_path, primary_color, timestamps, softDeletes`.
- **`organization_user`** (pivot) — `id, organization_id, user_id, role` (`owner|admin|coach|guardian`), `timestamps`.
- **`subscriptions`** (Cashier) — `id, organization_id, type, stripe_id, stripe_status, stripe_price, quantity, trial_ends_at, ends_at, timestamps`.

### Org settings
- **`seasons`** — `id, organization_id, name, start_date, end_date, is_active (only one per org), is_registration_open, timestamps`.
- **`divisions`** — `id, organization_id, name, display_order, timestamps`.
- **`locations`** — `id, organization_id, name, address, maps_link, timestamps`.

### Roster & teams
- **`players`** — `id, organization_id, first_name, last_name, dob, graduation_year (nullable), bats (R|L|S), throws (R|L), notes, timestamps, softDeletes`. NO jersey number.
- **`guardians`** — `id, organization_id, user_id (nullable — null = unclaimed), first_name, last_name, email, phone, timestamps`.
- **`guardian_player`** (pivot) — `id, guardian_id, player_id, relationship`.
- **`teams`** — `id, organization_id, season_id, division_id, name, head_coach_id → users (nullable), slug, timestamps`. Enable softDeletes.
- **`team_player`** (pivot/roster) — `id, team_id, player_id, jersey_number (nullable), primary_position (nullable), is_captain, timestamps`.

### Form builder
- **`forms`** — `id, organization_id, title, status (draft|published|closed), schema (JSON), price (cents), timestamps`.
- **`submissions`** — `id, form_id, user_id (nullable), player_id (nullable), data (JSON), payment_status (unpaid|paid), timestamps`.

Linked: [[architecture-tenancy]] for tenancy patterns, [[global-vs-seasonal]] for the seasonal/global split, [[form-builder]] for JSON-column rules.
