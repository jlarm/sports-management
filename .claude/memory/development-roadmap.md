---
name: development-roadmap
description: Build order for the SaaS — gate first, then org setup, then form builder, then rosters
metadata:
  type: project
---

Build in this order to minimize rework:

**1. SaaS onboarding (the gate).** Install Laravel Cashier. Signup creates a user + organization, redirects to Stripe Checkout, on success lands on dashboard. `CheckSubscription` middleware blocks everything if status ≠ `active`. Do this BEFORE any baseball-specific feature — it's pointless to build features users can't pay for.

**2. Org settings CRUD.** Admin sets up the foundational data: seasons, divisions, locations, invite assistant admins. Nothing useful can happen until these exist.

**3. Form builder.** Admin drag/drop builder for `forms.schema`. Public-facing Livewire registration page at `/org/{slug}/register`. Stripe Connect for parent→org payment. See [[form-builder]] and [[payments]].

**4. Player pool.** Submission processing creates/updates `Player` rows. This populates the "unassigned" pool the team manager works from. See [[players-guardians]].

**5. Team manager (the core value).** Admin creates teams within the active season. Dual-listbox UI (unassigned ↔ team roster) using Livewire for the move action. Exports: PDF rosters, CSV for tournaments.

**Cross-cutting:** session-based `current_org_id` and `current_season_id` (see [[architecture-tenancy]] and [[season-archiving]]) need to be wired in early so every later phase inherits the scoping for free.

Initial stack checklist: Laravel + Inertia/Vue (this project) + Tailwind + Cashier. Stripe product configured in dashboard before coding the signup flow.
